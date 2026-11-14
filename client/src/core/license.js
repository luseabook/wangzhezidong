// 授权客户端：激活、店铺位校验、解绑、心跳
// 授权中心不可达时：已激活且未过期的继续用（同步不受影响），但绑店会被拒绝。
import os from 'node:os';
import crypto from 'node:crypto';
import { loadConfig, saveConfig } from './config.js';

export class LicenseError extends Error {
  constructor(message, o = {}) {
    super(message);
    this.name = 'LicenseError';
    Object.assign(this, o);
  }
}

const TIMEOUT_MS = 6000;

function base(cfg) {
  return String(cfg.license_server || '').replace(/\/+$/, '');
}

/** 稳定的机器标识：主机名 + 首次生成的随机串，存本地 */
export function machineId(cfg) {
  if (cfg.machine_id) return cfg.machine_id;
  const host = (os.hostname() || 'pc').replace(/[^a-zA-Z0-9-]/g, '').slice(0, 16);
  const rnd = crypto.randomBytes(8).toString('hex');
  const id = `${host}-${rnd}`;
  const c = loadConfig();
  c.machine_id = id;
  saveConfig(c);
  return id;
}

async function call(cfg, path, body) {
  const b = base(cfg);
  if (!b) throw new LicenseError('未配置授权中心地址', { reason: 'NO_SERVER' });

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(b + path, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(body || {}),
      signal: ctrl.signal,
    });
    const text = await res.text();
    let json = null;
    try { json = JSON.parse(text); } catch { /* ignore */ }
    if (!json) throw new LicenseError(`授权中心响应异常 HTTP ${res.status}`, { reason: 'BAD_RESPONSE' });
    return json;
  } catch (e) {
    if (e instanceof LicenseError) throw e;
    throw new LicenseError('无法连接授权中心', { reason: 'UNREACHABLE', cause: e.message });
  } finally {
    clearTimeout(timer);
  }
}

/** 本地许可是否仍然可用（不看服务端，用于离线容错） */
export function localLicenseOk(cfg = loadConfig()) {
  const l = cfg.license;
  if (!l || !l.code) return { ok: false, reason: 'NOT_ACTIVATED' };
  if (l.status === 'suspended') return { ok: false, reason: 'SUSPENDED' };
  if (l.expires_at) {
    const t = new Date(`${l.expires_at}T23:59:59+08:00`).getTime();
    if (Date.now() > t) return { ok: false, reason: 'EXPIRED' };
  }
  return { ok: true, license: l };
}

/** 已过期但还在宽限期（用于界面提示） */
export function graceInfo(cfg = loadConfig()) {
  const l = cfg.license;
  if (!l?.expires_at) return null;
  const t = new Date(`${l.expires_at}T23:59:59+08:00`).getTime();
  const days = Math.ceil((t - Date.now()) / 86400000);
  if (days > 15) return null;
  return { days, expired: days < 0 };
}

export async function activate(code, version = '1.0.0') {
  const cfg = loadConfig();
  const mid = machineId(cfg);

  const j = await call(cfg, '/api/activate', {
    code: String(code || '').trim().toUpperCase(),
    machine_id: mid,
    version,
  });

  if (!j.ok) {
    throw new LicenseError(j.message || '激活失败', { reason: j.reason });
  }

  const c = loadConfig();
  c.license_server = base(c) || c.license_server;
  c.license = {
    code: String(code).trim().toUpperCase(),
    name: j.name || '',
    slots: Number(j.slots || 0),
    used: Number(j.used || 0),
    expires_at: j.expires_at || null,
    status: j.status || 'active',
    activated_at: new Date().toISOString(),
  };
  // 由授权中心下发的连接信息，覆盖本地默认值
  c.upload = c.upload || {};
  c.upload.base_url = j.base_url || c.upload.base_url || '';
  c.upload.token = j.upload_token || c.upload.token || '';
  saveConfig(c);

  return { license: c.license, base_url: c.upload.base_url };
}

/** 绑店前调用：返回 {ok:true} 或 {ok:false, reason, message} */
export async function bindShop(shopId, nick) {
  const cfg = loadConfig();
  const l = localLicenseOk(cfg);
  if (!l.ok) return { ok: false, reason: l.reason, message: reasonText(l.reason) };

  try {
    const j = await call(cfg, '/api/bind', {
      code: cfg.license.code,
      machine_id: machineId(cfg),
      shop_id: String(shopId),
      shop_nick: nick || '',
    });
    if (j.ok) {
      const c = loadConfig();
      c.license.slots = Number(j.slots ?? c.license.slots);
      c.license.used = Number(j.used ?? c.license.used);
      saveConfig(c);
    }
    return j;
  } catch (e) {
    // 授权中心不可达：保守拒绝绑店，但提示是网络问题
    return { ok: false, reason: 'UNREACHABLE', message: '暂时无法验证店铺位，请检查网络后重试' };
  }
}

export async function unbindShop(shopId) {
  const cfg = loadConfig();
  if (!cfg.license?.code) return { ok: true, skipped: true };
  try {
    const j = await call(cfg, '/api/unbind', { code: cfg.license.code, shop_id: String(shopId) });
    if (j.ok) {
      const c = loadConfig();
      c.license.used = Number(j.used ?? c.license.used);
      c.license.slots = Number(j.slots ?? c.license.slots);
      saveConfig(c);
    }
    return j;
  } catch {
    return { ok: false, reason: 'UNREACHABLE' };
  }
}

export async function heartbeat(activeShops = []) {
  const cfg = loadConfig();
  if (!cfg.license?.code) return null;
  try {
    const j = await call(cfg, '/api/heartbeat', {
      code: cfg.license.code,
      machine_id: machineId(cfg),
      version: '1.0.0',
      active_shops: activeShops.map(String),
    });
    if (j?.ok) {
      const c = loadConfig();
      c.license.slots = Number(j.slots ?? c.license.slots);
      c.license.used = Number(j.used ?? c.license.used);
      c.license.status = j.status || c.license.status;
      saveConfig(c);
    }
    return j;
  } catch {
    return null;
  }
}

export function reasonText(reason) {
  const m = {
    SLOTS_FULL: '店铺位已满，请联系服务商扩容',
    COOLDOWN: '刚更换过店铺，请稍后再试',
    DAILY_LIMIT: '今日更换店铺次数已达上限，请明日再试',
    EXPIRED: '授权已过期，请联系续费',
    SUSPENDED: '授权已被停用，请联系服务商',
    NOT_ACTIVATED: '尚未激活',
    UNREACHABLE: '无法连接授权中心，请检查网络',
    INVALID_CODE: '激活码无效',
  };
  return m[reason] || reason || '未知原因';
}