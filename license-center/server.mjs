// 授权中心 · HTTP 服务
// 零第三方依赖：node:http + node:sqlite
//
// 环境变量：
//   PORT            监听端口（默认 8899）
//   ADMIN_PASSWORD  后台登录密码（默认 ks-admin-2026，生产必须改）
//   DB_PATH         SQLite 路径（默认 ./data/license.db）
//   SECRET          会话签名密钥（默认取 ADMIN_PASSWORD）
import http from 'node:http';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { openDb, makeApi } from './db.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.PORT || 8899);
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'ks-admin-2026';
const DB_PATH = process.env.DB_PATH || path.join(__dirname, 'data', 'license.db');
const SECRET = process.env.SECRET || ADMIN_PASSWORD;
// 反向代理前缀（例如挂在 https://host/lic/ 后面时设为 /lic）
const BASE = (process.env.BASE_PATH || '').replace(/\/+$/, '');
const P = (p) => BASE + p;   // 生成带前缀的绝对路径（仅用于跳转）

// B 方案防滥用参数
const MAX_SWITCH_PER_DAY = Number(process.env.MAX_SWITCH_PER_DAY || 10);
const SWITCH_COOLDOWN_MIN = Number(process.env.SWITCH_COOLDOWN_MIN || 10);

const db = openDb(DB_PATH);
const api = makeApi(db);

const sign = (v) => crypto.createHmac('sha256', SECRET).update(String(v)).digest('hex').slice(0, 32);
const adminCookie = () => `ks_admin=${sign('admin')}`;
const isAdmin = (req) => (req.headers.cookie || '').includes(adminCookie());

function adminLoginPage(error = '') {
  return `<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>授权中心 · 登录</title>
<link rel="icon" href="data:,">
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#0a0a0a;color:#0a0a0a;font-family:"Noto Sans SC","PingFang SC","Microsoft YaHei",sans-serif;display:grid;place-items:center;overflow:hidden}
  body::before{content:"";position:fixed;inset:0;background-image:radial-gradient(rgba(255,255,255,.08) 1px,transparent 1px);background-size:24px 24px;pointer-events:none}
  .card{position:relative;z-index:1;width:min(390px,calc(100vw - 32px));background:#fff;padding:30px 30px 24px;box-shadow:0 30px 90px rgba(0,0,0,.55)}
  .brand{display:flex;align-items:center;gap:11px;margin-bottom:26px}
  .mark{width:36px;height:36px;border:1px solid #0a0a0a;display:grid;place-items:center}
  .mark svg{width:21px;height:21px;stroke:#0a0a0a;fill:none;stroke-width:1.4}
  .wordmark b{display:block;font-size:14px;font-weight:600;letter-spacing:.04em}
  .wordmark span{display:block;margin-top:4px;font:9px/1.4 ui-monospace,Consolas,monospace;letter-spacing:.16em;color:#8a8a86}
  h1{font-size:22px;line-height:1.2;font-weight:600;letter-spacing:-.02em;margin:0}
  .sub{margin:8px 0 22px;color:#8a8a86;font-size:11.5px;line-height:1.6}
  label{display:block;font:9px/1.4 ui-monospace,Consolas,monospace;letter-spacing:.14em;color:#8a8a86;margin-bottom:7px}
  input{width:100%;padding:11px 12px;border:1px solid #deded8;background:#fff;border-radius:0;font:13px/1.4 inherit;color:#0a0a0a;outline:none}
  input:focus{border-color:#0a0a0a}
  button{margin-top:16px;width:100%;height:40px;border:1px solid #0a0a0a;background:#0a0a0a;color:#fff;font-size:12px;cursor:pointer;border-radius:999px;letter-spacing:.04em}
  button:hover{background:#262624}
  .err{margin:0 0 14px;padding:9px 10px;border-left:2px solid #a83a2a;background:#fbf1ef;color:#a83a2a;font-size:11.5px}
  .foot{display:flex;justify-content:space-between;gap:10px;margin-top:22px;padding-top:14px;border-top:1px solid #deded8;color:#b4b4ae;font:8.5px/1.5 ui-monospace,Consolas,monospace;letter-spacing:.08em}
</style></head>
<body><div class="card">
  <div class="brand"><span class="mark"><svg viewBox="0 0 24 24"><path d="M6 3.5h9.5L19 7v13.5H6z"/><path d="M9 9h7M9 12.5h7M9 16h4.5"/></svg></span>
    <div class="wordmark"><b>授权中心</b><span>LICENSE LEDGER</span></div></div>
  <h1>管理后台</h1>
  <p class="sub">仅限授权管理员访问。请输入管理密码。</p>
  ${error ? `<div class="err">${error}</div>` : ''}
  <form method="post" action="${P('/admin/login')}">
    <label for="password">ADMIN PASSWORD</label>
    <input id="password" type="password" name="password" placeholder="管理密码" autocomplete="current-password" autofocus required>
    <button type="submit">进入授权账本</button>
  </form>
  <div class="foot"><span>AUTHORIZED ACCESS ONLY</span><span>NODE 24 / SQLITE</span></div>
</div></body></html>`;
}

function json(res, code, body) {
  const s = JSON.stringify(body);
  res.writeHead(code, {
    'content-type': 'application/json; charset=utf-8',
    'cache-control': 'no-store',
    'content-length': Buffer.byteLength(s),
  });
  res.end(s);
}
function html(res, code, s) {
  res.writeHead(code, { 'content-type': 'text/html; charset=utf-8' });
  res.end(s);
}
function readBody(req) {
  return new Promise((resolve) => {
    let b = '';
    req.on('data', (c) => { b += c; if (b.length > 2e6) req.destroy(); });
    req.on('end', () => {
      try { resolve(b ? JSON.parse(b) : {}); } catch { resolve({}); }
    });
  });
}
function readForm(req) {
  return new Promise((resolve) => {
    let b = '';
    req.on('data', (c) => { b += c; });
    req.on('end', () => {
      const o = {};
      for (const kv of b.split('&')) {
        if (!kv) continue;
        const [k, v] = kv.split('=');
        o[decodeURIComponent(k)] = decodeURIComponent((v || '').replace(/\+/g, ' '));
      }
      resolve(o);
    });
  });
}

const clientIp = (req) => (req.headers['x-forwarded-for'] || req.socket.remoteAddress || '').toString().split(',')[0].trim();

/** 统一的客户状态判定 */
function denyIfNotUsable(c) {
  const st = api.effectiveStatus(c);
  if (st === 'suspended') return { ok: false, reason: 'SUSPENDED', message: '该授权已被停用，请联系服务商' };
  if (st === 'expired') return { ok: false, reason: 'EXPIRED', message: '授权已过期，请联系续费' };
  return null;
}

// ---------------- 客户端接口 ----------------

async function handleActivate(req, res) {
  const b = await readBody(req);
  const c = api.clientByCode(b.code);
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE', message: '激活码无效' });

  const d = api.touchDevice(c.id, String(b.machine_id || ''), String(b.version || ''), clientIp(req));
  if (d.count > 1) {
    api.alert(c.id, 'multi_device', 'warn', `同一激活码已在 ${d.count} 台设备上激活`, { machineId: b.machine_id });
  }

  const st = api.effectiveStatus(c);
  api.log(c.id, 'activate', { machine: b.machine_id, version: b.version, ip: clientIp(req) });

  return json(res, 200, {
    ok: st !== 'suspended',
    status: st,
    reason: st === 'suspended' ? 'SUSPENDED' : undefined,
    message: st === 'suspended' ? '该授权已被停用' : undefined,
    name: c.name,
    base_url: c.base_url,
    upload_token: c.upload_token,
    slots: c.slots,
    used: api.used(c.id),
    expires_at: c.expires_at,
  });
}

async function handleBind(req, res) {
  const b = await readBody(req);
  const c = api.clientByCode(b.code);
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE' });

  const denied = denyIfNotUsable(c);
  if (denied) return json(res, 200, denied);

  const shopId = String(b.shop_id || '').trim();
  if (!/^\d{6,24}$/.test(shopId)) return json(res, 200, { ok: false, reason: 'BAD_SHOP_ID' });

  const used = api.used(c.id);

  // 已占用：幂等返回
  if (api.isBound(c.id, shopId)) {
    api.log(c.id, 'bind_exists', { shop: shopId });
    return json(res, 200, { ok: true, used, slots: c.slots, exists: true });
  }

  // 店铺位
  if (used >= c.slots) {
    api.recordBindAttempt(c.id);
    if (api.bindAttemptsRecent(c.id, 10) >= 3) {
      api.alert(c.id, 'slots_full_retry', 'warn', `店铺位已满（${used}/${c.slots}）仍反复尝试绑定`, { shop: shopId });
    }
    return json(res, 200, {
      ok: false, reason: 'SLOTS_FULL',
      used, slots: c.slots,
      message: `店铺位已满（${used}/${c.slots}），请联系服务商扩容`,
    });
  }

  // 换店冷却
  const last = api.lastSwitchAt(c.id);
  if (last && Date.now() - last < SWITCH_COOLDOWN_MIN * 60000) {
    const wait = Math.ceil((SWITCH_COOLDOWN_MIN * 60000 - (Date.now() - last)) / 1000);
    return json(res, 200, {
      ok: false, reason: 'COOLDOWN', retry_after: wait,
      used, slots: c.slots,
      message: `刚更换过店铺，请 ${Math.ceil(wait / 60)} 分钟后再试`,
    });
  }

  // 每日换店次数
  const switches = api.switchesToday(c.id);
  if (switches >= MAX_SWITCH_PER_DAY) {
    api.alert(c.id, 'switch_limit', 'warn', `今日换店已达上限（${switches}/${MAX_SWITCH_PER_DAY}）`, { shop: shopId });
    return json(res, 200, {
      ok: false, reason: 'DAILY_LIMIT',
      used, slots: c.slots,
      message: `今日更换店铺已达 ${MAX_SWITCH_PER_DAY} 次上限，请明日再试`,
    });
  }

  api.bind(c.id, shopId, String(b.shop_nick || ''), 'client');
  api.log(c.id, 'bind', { shop: shopId, nick: b.shop_nick });
  return json(res, 200, { ok: true, used: api.used(c.id), slots: c.slots });
}

async function handleUnbind(req, res) {
  const b = await readBody(req);
  const c = api.clientByCode(b.code);
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE' });
  const ok = api.unbind(c.id, String(b.shop_id || ''));
  api.log(c.id, 'unbind', { shop: b.shop_id });
  return json(res, 200, { ok, used: api.used(c.id), slots: c.slots });
}

async function handleHeartbeat(req, res) {
  const b = await readBody(req);
  const c = api.clientByCode(b.code);
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE' });

  api.touchDevice(c.id, String(b.machine_id || ''), String(b.version || ''), clientIp(req));
  for (const s of (b.active_shops || [])) {
    if (api.isBound(c.id, String(s))) api.touchBinding(c.id, String(s));
  }
  return json(res, 200, { ok: true, slots: c.slots, used: api.used(c.id), status: api.effectiveStatus(c) });
}

/**
 * 结算系统回传：本次导入了哪些店铺。
 * 这是"破解客户端也发现超配"的关键 —— 客户端不参与。
 */
async function handleReportShops(req, res) {
  const b = await readBody(req);
  const c = api.clientByCode(b.client_code);
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE' });

  const shops = Array.isArray(b.shop_ids) ? [...new Set(b.shop_ids.map(String))] : [];
  const unknown = [];

  for (const s of shops) {
    if (!api.isBound(c.id, s)) {
      // 未在授权范围出现 → 登记为占用（计入 used），让配额反映真实情况
      api.bind(c.id, s, '', 'report');
      unknown.push(s);
    } else {
      api.touchBinding(c.id, s);
    }
  }

  const used = api.used(c.id);
  const over = used > c.slots;

  if (unknown.length) {
    api.alert(c.id, 'unauthorized_shop', 'high',
      `发现 ${unknown.length} 个未授权店铺在导入数据：${unknown.join(', ')}`,
      { shops: unknown, used, slots: c.slots });
  }
  if (over) {
    api.alert(c.id, 'over_slots', 'high', `占用超限：已用 ${used} / 授权 ${c.slots}`, { used, slots: c.slots });
  }

  api.log(c.id, 'report_shops', { shops, used, over });
  return json(res, 200, { ok: true, used, slots: c.slots, over });
}

function handleLicense(req, res, url) {
  const c = api.clientByCode(url.searchParams.get('code'));
  if (!c) return json(res, 200, { ok: false, reason: 'INVALID_CODE' });
  return json(res, 200, {
    ok: true,
    status: api.effectiveStatus(c),
    expires_at: c.expires_at,
    slots: c.slots,
    used: api.used(c.id),
  });
}

// ---------------- 后台 ----------------

const ADMIN_HTML = path.join(__dirname, 'admin.html');

function adminStats() {
  const list = api.listClients();
  return {
    clients: list.length,
    slots: list.reduce((a, c) => a + c.slots, 0),
    used: list.reduce((a, c) => a + c.used, 0),
    open_alerts: api.listAlerts({ onlyOpen: true }).length,
    suspended: list.filter((c) => c.status === 'suspended').length,
  };
}

async function handleAdminApi(req, res, url) {
  if (!isAdmin(req)) return json(res, 401, { ok: false, reason: 'UNAUTH' });

  // 反向代理前缀在这里也必须先去掉；否则 BASE_PATH=/lic 时
  // /lic/admin/api/overview 会被当成 /lic/overview，后台接口全部 404。
  let path = url.pathname;
  if (BASE && (path === BASE || path.startsWith(BASE + '/'))) {
    path = path.slice(BASE.length) || '/';
  }
  const p = path.replace('/admin/api/', '');
  const method = req.method;

  if (method === 'GET' && p === 'overview') {
    return json(res, 200, {
      ok: true,
      env: process.env.LICENSE_ENV || process.env.NODE_ENV || 'TEST',
      stats: adminStats(),
      clients: api.listClients(),
      alerts: api.listAlerts({ onlyOpen: true, limit: 50 }),
      switches: { perDay: MAX_SWITCH_PER_DAY, cooldownMin: SWITCH_COOLDOWN_MIN },
    });
  }

  if (method === 'GET' && p === 'events') {
    const limit = Math.min(Math.max(Number(url.searchParams.get('limit') || 20), 1), 100);
    const events = api.q(`SELECT e.*, c.name AS client_name, c.code AS client_code
                          FROM events e LEFT JOIN clients c ON c.id=e.client_id
                          ORDER BY e.id DESC LIMIT ?`, limit);
    return json(res, 200, { ok: true, events });
  }

  if (method === 'GET' && p === 'client') {
    const id = Number(url.searchParams.get('id'));
    const c = api.clientById(id);
    if (!c) return json(res, 404, { ok: false });
    return json(res, 200, {
      ok: true,
      client: { ...c, used: api.used(c.id), status_effective: api.effectiveStatus(c), switches_today: api.switchesToday(c.id) },
      bindings: api.activeShops(c.id),
      devices: api.q('SELECT * FROM devices WHERE client_id=? ORDER BY last_seen DESC', c.id),
      alerts: api.q('SELECT * FROM alerts WHERE client_id=? ORDER BY id DESC LIMIT 30', c.id),
      events: api.q('SELECT * FROM events WHERE client_id=? ORDER BY id DESC LIMIT 30', c.id),
    });
  }

  if (method === 'POST' && p === 'client') {
    // 保存客户（新建或修改）
    const b = await readBody(req);
    if (b.id) {
      const before = api.clientById(Number(b.id));
      const after = api.updateClient(Number(b.id), {
        name: b.name, base_url: b.base_url, upload_token: b.upload_token,
        slots: b.slots, status: b.status, expires_at: b.expires_at || null, note: b.note,
      });
      if (before && after && Number(before.slots) !== Number(after.slots)) {
        api.log(after.id, 'slots_change', { from: before.slots, to: after.slots });
      }
      return json(res, 200, { ok: true, client: after });
    }
    const c = api.createClient({
      code: b.code, name: b.name, base_url: b.base_url, upload_token: b.upload_token,
      slots: b.slots || 3, expires_at: b.expires_at || null, note: b.note || '',
    });
    api.log(c.id, 'create', { name: c.name, slots: c.slots });
    return json(res, 200, { ok: true, client: c });
  }

  if (method === 'POST' && p === 'alert/ack') {
    const b = await readBody(req);
    api.ackAlert(Number(b.id));
    return json(res, 200, { ok: true });
  }

  if (method === 'POST' && p === 'unbind') {
    const b = await readBody(req);
    api.unbind(Number(b.client_id), String(b.shop_id));
    return json(res, 200, { ok: true });
  }

  return json(res, 404, { ok: false });
}

// ---------------- 路由 ----------------

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  // 去掉反向代理前缀，内部一律按无前缀路径路由
  let p = url.pathname;
  if (BASE && (p === BASE || p.startsWith(BASE + '/'))) {
    p = p.slice(BASE.length) || '/';
  }

  try {
    if (p === '/api/activate' && req.method === 'POST') return await handleActivate(req, res);
    if (p === '/api/bind' && req.method === 'POST') return await handleBind(req, res);
    if (p === '/api/unbind' && req.method === 'POST') return await handleUnbind(req, res);
    if (p === '/api/heartbeat' && req.method === 'POST') return await handleHeartbeat(req, res);
    if (p === '/api/report-shops' && req.method === 'POST') return await handleReportShops(req, res);
    if (p === '/api/license' && req.method === 'GET') return handleLicense(req, res, url);

    if (p === '/health') return json(res, 200, { ok: true, clients: api.listClients().length });

    if (p === '/admin/login' && req.method === 'POST') {
      const f = await readForm(req);
      if (f.password === ADMIN_PASSWORD) {
        res.writeHead(302, { 'set-cookie': `${adminCookie()}; Path=/; HttpOnly; Max-Age=86400`, location: P('/admin') });
        return res.end();
      }
      return html(res, 401, adminLoginPage('密码错误，请重试。'));
    }

    if (p === '/admin') {
      if (!isAdmin(req)) {
        return html(res, 200, adminLoginPage());
      }
      // 注入 <base>，页面里的相对路径就能在反向代理前缀下正常工作
      const body = fs.readFileSync(ADMIN_HTML, 'utf8').replace('<head>', `<head><base href="${P('/')}">`);
      return html(res, 200, body);
    }

    if (p.startsWith('/admin/api/')) return await handleAdminApi(req, res, url);

    return json(res, 404, { ok: false, reason: 'NOT_FOUND' });
  } catch (e) {
    console.error('[error]', req.method, p, e);
    return json(res, 500, { ok: false, reason: 'SERVER_ERROR', message: e.message });
  }
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`授权中心已启动: http://127.0.0.1:${PORT}/admin`);
  console.log(`  DB: ${DB_PATH}`);
  console.log(`  每日换店上限: ${MAX_SWITCH_PER_DAY} 次 / 冷却: ${SWITCH_COOLDOWN_MIN} 分钟`);
});