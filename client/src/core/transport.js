// 传输层：全部走 Electron net（自动带该 partition 的 Cookie）
// 红线：pageSize 恒为 10；严格串行；间隔 >= request_delay_ms；400002 立即中止整轮
import { net } from 'electron';

export class RiskControlError extends Error {
  constructor(msg, o = {}) { super(msg); this.name = 'RiskControlError'; Object.assign(this, o); }
}
export class CredentialError extends Error {
  constructor(msg, o = {}) { super(msg); this.name = 'CredentialError'; Object.assign(this, o); }
}
export class BizError extends Error {
  constructor(msg, o = {}) { super(msg); this.name = 'BizError'; Object.assign(this, o); }
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const RESULT = { OK: 1, RISK: 400002, NO_SESSION: 7210 };

export function dayRange(dateStr) {
  return {
    start: new Date(`${dateStr}T00:00:00+08:00`).getTime(),
    end: new Date(`${dateStr}T23:59:59+08:00`).getTime(),
  };
}

export function dateList(fromDate, toDate, maxDays = 400) {
  const out = [];
  const p = (n) => String(n).padStart(2, '0');
  let cur = new Date(`${fromDate}T12:00:00+08:00`);
  const end = new Date(`${toDate}T12:00:00+08:00`);
  while (cur <= end && out.length < Number(maxDays || 400)) {
    out.push(`${cur.getFullYear()}-${p(cur.getMonth() + 1)}-${p(cur.getDate())}`);
    cur = new Date(cur.getTime() + 86400000);
  }
  return out;
}

function httpPost(session, sign, bodyStr) {
  return new Promise((resolve, reject) => {
    const req = net.request({ method: sign.method || 'POST', url: sign.url, session, useSessionCookies: true });
    for (const [k, v] of Object.entries(sign.headers || {})) {
      if (/^(host|content-length|connection|accept-encoding)$/i.test(k)) continue;
      try { req.setHeader(k, v); } catch { /* ignore */ }
    }
    req.setHeader('Content-Type', 'application/json;charset=UTF-8');
    let buf = '';
    req.on('response', (res) => {
      res.on('data', (d) => { buf += d.toString('utf8'); });
      res.on('end', () => resolve({ status: res.statusCode, text: buf }));
    });
    req.on('error', (e) => reject(new CredentialError(`网络错误: ${e.message}`)));
    req.write(bodyStr);
    req.end();
  });
}

function httpPostPlain(session, path, bodyStr) {
  return new Promise((resolve, reject) => {
    const req = net.request({ method: 'POST', url: 'https://s.kwaixiaodian.com' + path, session, useSessionCookies: true });
    req.setHeader('Content-Type', 'application/json;charset=UTF-8');
    req.setHeader('Accept', 'application/json');
    let buf = '';
    req.on('response', (res) => {
      res.on('data', (d) => { buf += d.toString('utf8'); });
      res.on('end', () => resolve({ status: res.statusCode, text: buf }));
    });
    req.on('error', (e) => reject(new CredentialError(`网络错误: ${e.message}`)));
    req.write(bodyStr);
    req.end();
  });
}

export class Transport {
  constructor(cfg, session, sign, { log = () => {} } = {}) {
    this.cfg = cfg;
    this.session = session;
    this.sign = sign;
    this.log = log;
    this.pagesFetched = 0;
  }

  async post(body) {
    const { request_delay_ms = 1300, page_size = 10 } = this.cfg.sync || {};
    if (page_size !== 10) throw new Error(`page_size 必须为 10，当前 ${page_size}`);

    await sleep(request_delay_ms);
    const { status, text } = await httpPost(this.session, this.sign, JSON.stringify(body));

    let json = null;
    try { json = JSON.parse(text); } catch { /* 非 JSON */ }

    if (!json) {
      if (status === 401 || status === 403) throw new CredentialError(`HTTP ${status}`);
      if (/login\.kwaixiaodian|请登录/.test(text)) throw new CredentialError('被踢回登录页');
      throw new BizError(`非 JSON 响应 HTTP ${status}`);
    }

    const result = json.result ?? json.code;
    if (result === RESULT.RISK) {
      throw new RiskControlError(json.error_msg || '风控', { requestId: json.requestId });
    }
    if (result === RESULT.NO_SESSION) {
      throw new CredentialError(json.error_msg || '缺少会话/CSRF', { requestId: json.requestId });
    }
    if (result !== RESULT.OK) {
      throw new BizError(`${json.error_msg || '业务失败'} (result=${result})`, { result, requestId: json.requestId });
    }

    this.log(`[fetch] page=${body.orderSearchParam.current} ${text.length}B total=${json.data?.total}`);
    return json.data || {};
  }

  fetchPage({ startMs, endMs, current = 1, status = '0' }) {
    return this.post({
      orderSearchParam: {
        current, pageSize: 10, status, queryRange: '1',
        userType: 'domestic', startTime: startMs, endTime: endMs,
      },
    });
  }

  /**
   * 订单详情（补发货时间/真实支付时间）
   *
   * 与列表接口不同：详情不需要 KS 签名，只要会话 Cookie。
   * 实测 result=1；返回的是 UI 组件树，解析见 core/detail.js。
   */
  async fetchDetail(oid) {
    const delay = this.cfg?.sync?.request_delay_ms ?? 1300;
    await sleep(delay);

    const { status, text } = await httpPostPlain(
      this.session,
      '/gateway/app/tts/ks/seller/order/detail/v2',
      JSON.stringify({ oid: String(oid) }),
    );

    let json = null;
    try { json = JSON.parse(text); } catch { /* 非 JSON */ }

    if (!json) {
      if (status === 401 || status === 403) throw new CredentialError(`HTTP ${status}`);
      throw new BizError(`详情非 JSON 响应 HTTP ${status}`);
    }

    const result = json.result ?? json.code;
    if (result === RESULT.RISK) throw new RiskControlError(json.error_msg || '风控', { requestId: json.requestId });
    if (result === RESULT.NO_SESSION) throw new CredentialError(json.error_msg || '缺少会话', { requestId: json.requestId });
    if (result !== RESULT.OK) throw new BizError(`${json.error_msg || '详情失败'} (result=${result})`, { result });

    return json;
  }

  /** 拉完整时间范围 */
  async fetchRange({ startMs, endMs, status = '0', maxPages = 600, onPage = null }) {
    const orders = [];
    let total = 0, pages = 0, truncated = false;
    for (let current = 1; current <= maxPages; current++) {
      const data = await this.fetchPage({ startMs, endMs, current, status });
      const list = data.orderList || [];
      total = data.total ?? total;
      pages += 1;
      this.pagesFetched += 1;
      orders.push(...list);
      if (onPage) await onPage({ current, list, total, got: orders.length });
      if (!list.length) break;
      if (orders.length >= total) break;
      if (current === maxPages) { truncated = true; this.log(`[fetch] 触达单轮页数上限 ${maxPages}，截断`); }
    }
    return { orders, total, pages, truncated };
  }

  /** 按天切片（单天量大时更稳） */
  async fetchByDay({ dates, status = '0', onDay = null }) {
    const all = [];
    for (const d of dates) {
      const { start, end } = dayRange(d);
      const r = await this.fetchRange({ startMs: start, endMs: end, status });
      if (onDay) await onDay({ date: d, ...r });
      all.push(...r.orders);
      if (r.truncated) break;
    }
    return { orders: all };
  }
}