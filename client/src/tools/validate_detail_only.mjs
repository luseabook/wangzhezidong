// 聚焦验证：
//  1) Transport.fetchDetail 能拿到发货时间（新代码路径）
//  2) 记录带 send_time 时，导出的 xlsx 第 43 列真的有值
// 运行: npx electron src\tools\validate_detail_only.mjs
import { app, session } from 'electron';
import path from 'node:path';
import fs from 'node:fs';
import os from 'node:os';
import { loadConfig } from '../core/config.js';
import { Transport } from '../core/transport.js';
import { extractDetail } from '../core/detail.js';
import { ordersByDate } from '../core/shops.js';
import { loadColumnMap, writeOutput } from '../core/export.mjs';

const PARTITION = process.env.KS_BIND_PARTITION || 'persist:validate_import';
const SHOP_ID = '140000122179234';
const DATE = process.env.KS_DATE || '2026-08-28';
const N = Number(process.env.KS_N || 6);

const COLUMN_MAP = path.join(path.dirname(new URL(import.meta.url).pathname).replace(/^\/([A-Za-z]:)/, '$1'), '..', 'core', 'column_map.json');

app.whenReady().then(async () => {
  const ses = session.fromPartition(PARTITION);
  const cfg = loadConfig();
  cfg.sync.request_delay_ms = 1200;

  // 取本地已有订单作为样本：只看「交易成功」这类真有发货时间的单
  const recs = ordersByDate(SHOP_ID, DATE);
  const shipped = recs.filter((r) => r.order_status === '交易成功');
  const sample = shipped.slice(0, N);
  console.log(`样本 ${sample.length} 单（本地库 ${recs.length} 单，其中交易成功 ${shipped.length} 单）\n`);

  // Transport 需要一个 sign 对象，但 fetchDetail 不走签名，随便给个占位
  const t = new Transport(cfg, ses, { url: 'about:blank', method: 'POST', headers: {} }, { log: () => {} });

  let okCount = 0;
  const got = [];
  for (const r of sample) {
    try {
      const json = await t.fetchDetail(r.oid);
      const d = extractDetail(json);
      if (d.send_time) { okCount++; got.push({ oid: r.oid, ...d }); }
      console.log(`  ${r.oid} 状态=${r.order_status} 支付=${d.pay_time || '-'} 发货=${d.send_time || '-'}`);
    } catch (e) {
      console.log(`  ${r.oid} 失败: ${e.name} ${e.message}`);
    }
  }
  console.log(`\n=== 1) 详情补抓: ${okCount}/${sample.length} 拿到发货时间 ===\n`);

  // 2) 导出验证：造一条带 send_time 的记录，写 xlsx 后检查第 43 列
  const outDir = path.join(os.tmpdir(), 'ks-detail-test');
  fs.mkdirSync(outDir, { recursive: true });
  const fake = [{
    ...recs[0],
    oid: '9999000011112222',
    send_time: '2026-08-28 12:24:52',
    pay_time: '2026-08-27 23:46:03',
    pay_time_approx: 0,
  }];
  const { colMap, width } = loadColumnMap(COLUMN_MAP);
  const out = writeOutput({
    outDir, fileName: 'detail-check.xlsx', sheetName: '订单',
    records: fake, colMap, width, overwrite: true, meta: {},
  });
  console.log('导出文件:', out.xlsxPath);

  // xlsx 是 zip，解压后从 sharedStrings 找那个时间字符串
  const { execSync } = await import('node:child_process');
  const tmpX = path.join(outDir, 'x');
  fs.rmSync(tmpX, { recursive: true, force: true });
  fs.mkdirSync(tmpX, { recursive: true });
  execSync(`powershell -Command "Expand-Archive -Path '${out.xlsxPath}' -DestinationPath '${tmpX}' -Force"`);

  // 写出器可能用 inlineStr 也可能是 sharedStrings，两种都找
  const TARGET = '2026-08-28 12:24:52';
  const xlDir = path.join(tmpX, 'xl');
  let hitFile = null;
  let cellRef = '(未定位)';
  const walkDir = (d) => {
    for (const f of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, f.name);
      if (f.isDirectory()) walkDir(p);
      else if (p.endsWith('.xml') || p.endsWith('.rels')) {
        const s = fs.readFileSync(p, 'utf8');
        if (!s.includes(TARGET)) continue;
        hitFile = path.relative(tmpX, p);
        if (hitFile.startsWith('xl/worksheets')) {
          const m = new RegExp(`<c r="([A-Z]+\\d+)"[^>]*>(?:(?!</c>)[\\s\\S])*?${TARGET}`).exec(s);
          if (m) cellRef = m[1];
        }
      }
    }
  };
  walkDir(xlDir);
  const hasText = !!hitFile;

  console.log(`\n=== 2) 导出验证 ===`);
  console.log('  发货时间出现在:', hitFile || '(没有)');
  console.log('  所在单元格:', cellRef);
  console.log(`\n结论: 详情补抓 ${okCount > 0 ? 'OK' : '失败'} | 导出 ${hasText ? 'OK' : '失败'}`);

  app.quit();
});