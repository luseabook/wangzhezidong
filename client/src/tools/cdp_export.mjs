// 从已登录的 Chrome(CDP) 导出 cookie + 已签名请求，供 Electron 侧验证
// 运行： node src/tools/cdp_export.mjs [port]
import fs from 'node:fs';
import path from 'node:path';

const PORT = process.argv[2] || '9222';
const OUT = path.join(process.cwd(), 'probe-out', 'chrome-session.json');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function listTargets() {
  const r = await fetch(`http://127.0.0.1:${PORT}/json/list`);
  return r.json();
}

class Cdp {
  constructor(wsUrl) {
    this.ws = new WebSocket(wsUrl);
    this.id = 0;
    this.pending = new Map();
    this.handlers = new Map();
    this.ready = new Promise((res, rej) => {
      this.ws.onopen = () => res();
      this.ws.onerror = (e) => rej(new Error('WS error'));
    });
    this.ws.onmessage = (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
      } else if (msg.method && this.handlers.has(msg.method)) {
        for (const h of this.handlers.get(msg.method)) h(msg.params);
      }
    };
  }
  on(method, fn) {
    if (!this.handlers.has(method)) this.handlers.set(method, []);
    this.handlers.get(method).push(fn);
  }
  send(method, params = {}) {
    const id = ++this.id;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
  close() { try { this.ws.close(); } catch { /* ignore */ } }
}

const main = async () => {
  let targets = await listTargets();
  let page = targets.find((t) => t.type === 'page' && /kwaixiaodian/.test(t.url || ''));
  if (!page) {
    const r = await fetch(`http://127.0.0.1:${PORT}/json/new?url=${encodeURIComponent('https://s.kwaixiaodian.com/zone/order/list')}`, { method: 'PUT' });
    page = await r.json();
    await sleep(2000);
  }
  console.log('使用页面:', page.url);

  const cdp = new Cdp(page.webSocketDebuggerUrl);
  await cdp.ready;

  let candidates = [];
  let extraHeaders = new Map();   // requestId -> headers

  cdp.on('Network.requestWillBeSent', (p) => {
    const u = p.request?.url || '';
    if (u.includes('__NS_hxfalcon') && u.includes('/gateway/')) {
      candidates.push({ url: u, method: p.request.method, requestId: p.requestId, headers: { ...(p.request.headers || {}) } });
    }
  });
  cdp.on('Network.requestWillBeSentExtraInfo', (p) => {
    extraHeaders.set(p.requestId, p.headers);
  });

  await cdp.send('Network.enable', { maxPostDataSize: 65536 });
  await cdp.send('Page.enable');

  // 重新加载列表页，确保一定发出带签名的请求
  await cdp.send('Page.navigate', { url: 'https://s.kwaixiaodian.com/zone/order/list' });

  const t0 = Date.now();
  while (Date.now() - t0 < 25000) {
    await sleep(600);
    const hasV3 = candidates.some((c) => /order\/query\/v\d/.test(c.url));
    if (hasV3 && extraHeaders.size) break;
  }

  if (!candidates.length) { console.error('未截获任何签名请求，可能登录已失效'); process.exit(1); }

  // 优先订单列表接口（v2/v3...），其次任何 order 相关，最后兜底
  const pick = candidates.find((c) => /order\/query\/v\d/.test(c.url))
    || candidates.find((c) => /order\/query/.test(c.url) && !/\/view/.test(c.url))
    || candidates.find((c) => /\/order\//.test(c.url))
    || candidates[0];

  console.log('候选接口:');
  for (const c of candidates) console.log('  -', c.url.split('?')[0]);
  console.log('选用:', pick.url.split('?')[0]);

  const signed = pick;
  const extra = extraHeaders.get(pick.requestId) || null;

  const cookies = await cdp.send('Network.getCookies', { urls: ['https://s.kwaixiaodian.com/', 'https://id.kwaixiaodian.com/'] });

  const merged = { ...(signed.headers || {}), ...(extra || {}) };
  const keep = ['cookie', 'ks-csrf-token', 'merchantsessionkey', 'kpn', 'kpf', 'referer', 'user-agent', 'origin', 'content-type'];
  const headers = {};
  for (const [k, v] of Object.entries(merged)) {
    if (keep.includes(String(k).toLowerCase())) headers[k] = v;
  }

  const payload = {
    captured_at: new Date().toISOString(),
    sign: { url: signed.url, method: signed.method, headers },
    cookies: cookies.cookies,
  };
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(payload, null, 2), 'utf8');

  console.log(`cookie 数量: ${cookies.cookies.length}`);
  console.log(`header 数量: ${Object.keys(headers).length}`);
  console.log('已写入', OUT);
  cdp.close();
};

main().catch((e) => { console.error('失败:', e.message); process.exit(1); });