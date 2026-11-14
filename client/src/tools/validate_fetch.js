// 用导出的 Chrome 登录态验证 Electron 侧取数链路（net + session + 签名复用）
// 运行： node_modules\electron\dist\electron.exe src\tools\validate_fetch.js
import { app, session, net } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { Transport } from '../core/transport.js';
import { parseOrder } from '../core/parse.mjs';

const SESSION_FILE = path.join(process.cwd(), 'probe-out', 'chrome-session.json');
const log = (...a) => console.log('[validate]', ...a);

const mapSameSite = (s) => ({ Lax: 'lax', Strict: 'strict', None: 'no_restriction' }[s] || 'unspecified');

app.whenReady().then(async () => {
  const raw = JSON.parse(fs.readFileSync(SESSION_FILE, 'utf8'));
  const ses = session.fromPartition('persist:validate_import');

  // 1) 注入 cookie
  let n = 0;
  for (const c of raw.cookies) {
    const domain = String(c.domain || '').replace(/^\./, '');
    try {
      await ses.cookies.set({
        url: `${c.secure ? 'https' : 'http'}://${domain}${c.path || '/'}`,
        name: c.name, value: c.value,
        domain: c.domain, path: c.path || '/',
        secure: !!c.secure, httpOnly: !!c.httpOnly,
        expirationDate: c.expires && c.expires > 0 ? c.expires : undefined,
        sameSite: mapSameSite(c.sameSite),
      });
      n++;
    } catch (e) { log('cookie 跳过', c.name, e.message); }
  }
  log(`已注入 cookie ${n} 个`);

  const got = await ses.cookies.get({ domain: 'kwaixiaodian.com' });
  log(`session 内可见 cookie ${got.length} 个`);

  // 2) 用捕获的签名跑 Transport
  const cfg = { sync: { request_delay_ms: 500, page_size: 10 } };
  const sign = { url: raw.sign.url, method: raw.sign.method, headers: raw.sign.headers };
  const t = new Transport(cfg, ses, sign, { log });

  const today = new Date();
  const p = (x) => String(x).padStart(2, '0');
  const d = `${today.getFullYear()}-${p(today.getMonth() + 1)}-${p(today.getDate())}`;
  const start = new Date(`${d}T00:00:00+08:00`).getTime();
  const end = new Date(`${d}T23:59:59+08:00`).getTime();

  try {
    const data = await t.fetchPage({ startMs: start, endMs: end, current: 1, status: '0' });
    const keys = Object.keys(data || {});
    log('返回字段:', keys.join(','));
    const list = data.orderList || data.list || [];
    log(`total=${data.total} listLen=${list.length}`);
    if (list.length) {
      const rec = parseOrder(list[0], { shopId: 'probe' });
      log('首单解析:', JSON.stringify({
        oid: rec.oid, create_time: rec.create_time, status: rec.order_status,
        distributor_id: rec.distributor_id, price_cents: rec.price_cents,
        qty: rec.qty, actual_payment_cents: rec.actual_payment_cents,
      }));
      fs.writeFileSync(path.join(process.cwd(), 'probe-out', 'fetch-ok.json'), JSON.stringify({ ok: true, total: data.total, sample: rec }, null, 2));
    } else {
      fs.writeFileSync(path.join(process.cwd(), 'probe-out', 'fetch-ok.json'), JSON.stringify({ ok: true, total: data.total, note: '无订单数据（可能今日为0）', keys }, null, 2));
    }
    log('验证通过');
  } catch (e) {
    log('取数失败:', e.name, e.message);
    fs.writeFileSync(path.join(process.cwd(), 'probe-out', 'fetch-ok.json'), JSON.stringify({ ok: false, error: e.message, name: e.name }, null, 2));
  }

  app.quit();
});