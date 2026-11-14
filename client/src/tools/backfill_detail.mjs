// 详情补抓（补发货时间 / 真实支付时间）
//
// 用途：对本地已抓到的订单，逐单调用详情接口补齐列表接口给不了的字段。
// 正常同步时由 engine 自动执行；这个工具用于历史补齐或单独修复。
//
// 运行: npx electron src\tools\backfill_detail.mjs [shopId] [date] [cap]
import { app, session } from 'electron';
import { loadConfig } from '../core/config.js';
import { Transport } from '../core/transport.js';
import { extractDetail } from '../core/detail.js';
import { ordersByDate, upsertOrders } from '../core/shops.js';
import { FAILED_STATUS, rehash } from '../core/parse.mjs';

const PARTITION = process.env.KS_BIND_PARTITION || 'persist:validate_import';
const SHOP_ID = process.argv[2] || '140000122179234';
const DATE = process.argv[3] || '2026-08-28';
const CAP = Number(process.argv[4] || 400);

app.whenReady().then(async () => {
  const ses = session.fromPartition(PARTITION);
  const cfg = loadConfig();
  const delay = cfg.sync?.request_delay_ms ?? 1300;

  const recs = ordersByDate(SHOP_ID, DATE);
  const todo = recs.filter((r) => r.oid && !r.send_time && !FAILED_STATUS.has(r.order_status));
  console.log(`日期 ${DATE}：共 ${recs.length} 单，待补 ${todo.length} 单，本轮上限 ${CAP}`);
  console.log(`间隔 ${delay}ms，预计 ${Math.round((Math.min(todo.length, CAP) * delay) / 60000)} 分钟\n`);

  const t = new Transport(cfg, ses, { url: 'about:blank', method: 'POST', headers: {} }, { log: () => {} });
  let done = 0, filled = 0, pay = 0, fail = 0, risk = false;
  const t0 = Date.now();

  for (const r of todo.slice(0, CAP)) {
    try {
      const json = await t.fetchDetail(r.oid);
      const d = extractDetail(json);
      if (d.send_time) { r.send_time = d.send_time; filled++; }
      if (d.pay_time && r.pay_time_approx) { r.pay_time = d.pay_time; r.pay_time_approx = 0; pay++; }
    } catch (e) {
      if (e.name === 'RiskControlError') { console.log('  ⚠ 触发风控，立即停止'); risk = true; break; }
      if (e.name === 'CredentialError') { console.log('  ⚠ 会话失效，停止'); break; }
      fail++;
      if (fail >= 5) { console.log('  ⚠ 连续失败过多，停止'); break; }
    }
    done++;
    if (done % 25 === 0) {
      const el = Math.round((Date.now() - t0) / 1000);
      console.log(`  进度 ${done}/${Math.min(todo.length, CAP)} · 发货时间 ${filled} · 真实支付时间 ${pay} · ${el}s`);
    }
  }

  // 落库（只写回本地，不导出不上传）
  // 补抓改了字段，必须重算哈希，否则 upsert 会判定为「无变化」而跳过
  for (const r of todo.slice(0, done)) rehash(r);
  const stat = upsertOrders(SHOP_ID, todo.slice(0, done));
  console.log(`\n=== 完成 ===`);
  console.log(`  处理 ${done} 单，拿到发货时间 ${filled}，真实支付时间 ${pay}，失败 ${fail}${risk ? '（风控中断）' : ''}`);
  console.log(`  落库 update=${stat.updated} unchanged=${stat.unchanged}`);
  console.log(`  耗时 ${Math.round((Date.now() - t0) / 1000)}s`);

  app.quit();
});