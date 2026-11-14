// 自检：在不依赖扫码的前提下，验证「落库 → 产出 xlsx → 上传结算系统」全链
// 运行： node src/tools/selftest.js
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { loadConfig, setRoot } from '../core/config.js';
import { upsertOrders, ordersByDate, upsertShop, listShops } from '../core/shops.js';
import { loadColumnMap, writeOutput } from '../core/export.mjs';
import { uploadAndWait } from '../core/uploader.js';

const out = [];
const ok = (m) => { out.push(`  ✓ ${m}`); };
const bad = (m) => { out.push(`  ✗ ${m}`); process.exitCode = 1; };

console.log('=== 快手订单同步 客户端自检 ===\n');

// 1) 读取真实配置（用于上传）
const cfg = loadConfig();
console.log(`上传目标: ${cfg.upload.base_url || '(未配置)'}  token: ${cfg.upload.token ? '已配置' : '(未配置)'}`);

// 2) 切到隔离目录做落库/产出测试，避免污染真实数据
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ksselftest-'));
setRoot(tmp);
console.log(`隔离目录: ${tmp}\n`);

const SHOP = '999900001111222';
upsertShop({ id: SHOP, nick: '自检店铺' });

const mk = (oid, day, cents, qty, dist) => ({
  oid, create_time: `${day} 10:00:00`, pay_time: `${day} 10:00:00`, pay_time_approx: true,
  order_status: '交易成功', refund_status: '-', terminal: true,
  distributor_id: dist, distributor_name: `达人${dist}`, team_id: '', team_name: '',
  item_id: '26860000000001', item_title: '测试商品', sku_name: '',
  price_cents: cents, qty, actual_payment_cents: cents * qty,
  express_cents: 0, plat_discount_cents: 0, shop_discount_cents: 0, anchor_discount_cents: 0, pay_discount_cents: 0,
  commission_cents: Math.round(cents * qty * 0.05), commission_rate: '5%',
  consignee_mask: '张*', phone_mask: '138****0000',
  province: '广东省', city: '深圳市', district: '南山区', town: '', address_text: '测试地址',
  content_hash: `h_${oid}`,
});

const recs = [mk('A1', '2026-08-28', 3990, 1, '4319651210'), mk('A2', '2026-08-28', 3990, 10, '4319651210'), mk('A3', '2026-08-27', 1990, 2, '4331484231')];

// 落库
const s1 = upsertOrders(SHOP, recs);
s1.inserted === 3 ? ok(`首次落库 inserted=${s1.inserted}`) : bad(`首次落库异常 ${JSON.stringify(s1)}`);

const s2 = upsertOrders(SHOP, recs);
s2.unchanged === 3 && s2.inserted === 0 ? ok('幂等复跑 unchanged=3（不重复写）') : bad(`幂等失败 ${JSON.stringify(s2)}`);

const day = ordersByDate(SHOP, '2026-08-28');
day.length === 2 ? ok('按日期分片读取正常（8/28 共 2 单）') : bad(`分片读取异常 ${day.length}`);

// 产出
const { colMap, width } = loadColumnMap(path.join(path.dirname(new URL(import.meta.url).pathname).replace(/^\/([A-Za-z]:)/, '$1'), '..', 'core', 'column_map.json'));
const res = writeOutput({
  outDir: path.join(tmp, 'KsSync', 'shops', SHOP, 'out'),
  fileName: '自检_20260828.xlsx', sheetName: 'Sheet1',
  records: day, colMap, width,
  meta: { shop: '自检店铺', date: '2026-08-28' },
});
const stat = fs.statSync(res.xlsxPath);
stat.size > 1000 ? ok(`xlsx 产出 ${res.rows} 行 / ${(stat.size / 1024).toFixed(1)}KB`) : bad('xlsx 过小');
out.push(`    路径: ${res.xlsxPath}`);

// 3) 上传真实文件（使用之前已验证过的真实数据文件，避免伪造订单污染结算库）
const realFile = 'E:\\wangzhe\\ks_sync\\data\\shops\\ks_140000122179234\\out\\快手订单_自动_2号店罗伯特_20260828.xlsx';
if (cfg.upload.base_url && cfg.upload.token && fs.existsSync(realFile)) {
  const shop = listShops().find((s) => s.id === '140000122179234') || { id: '140000122179234', nick: '2号店' };
  try {
    const up = await uploadAndWait(cfg, shop, realFile, { log: (m) => out.push(`    ${m}`) });
    if (up.skipped) { out.push(`  - 上传跳过：${up.reason}`); }
    else if (up.finished && (up.fail || 0) === 0) ok(`上传+导入成功 log_id=${up.log_id} success=${up.success} fail=${up.fail} source=${up.source}`);
    else bad(`上传结果异常 ${JSON.stringify(up)}`);
  } catch (e) {
    bad(`上传失败: ${e.message}`);
  }
} else {
  out.push('  - 跳过上传（未配置地址/令牌或真实文件不存在）');
}

console.log(out.join('\n'));
console.log(`\n=== ${process.exitCode ? '有问题' : '全部通过'} ===`);

// 清理隔离目录
try { fs.rmSync(tmp, { recursive: true, force: true }); } catch { /* ignore */ }