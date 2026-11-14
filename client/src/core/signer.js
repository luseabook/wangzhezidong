// 签名层：截获页面自己发出的已签名请求，并复用。
// 实测结论（docs/Spike-判定记录.md）：
//   - __NS_hxfalcon 只绑定 URL path + 会话，不绑定 body
//   - 可复用，TTL 至少 20 分钟
//   - body 的 pageSize/current/status/时间范围可自由改
//
// 关键坑：页面同时会发 /order/query/view（页面配置）和 /order/query/v3（真正的列表）。
// 两者签名不通用（绑定 path），必须显式挑选版本化列表接口，否则取数返回的是配置数据。
import fs from 'node:fs';
import path from 'node:path';
import { shopDir } from './shops.js';

const TTL_MS = 20 * 60 * 1000;
const candidates = new Map();   // shopId -> Map(path -> sign)
const attached = new Map();     // shopId -> boolean

const isListEndpoint = (u) => /\/order\/query\/v\d+/.test(u);
const isQueryEndpoint = (u) => /\/order\/query\//.test(u) && !/\/order\/query\/view/.test(u);

/** 从候选中挑最合适的签名 */
export function pickSign(shopId) {
  const m = candidates.get(shopId);
  if (!m || !m.size) return null;
  const all = [...m.values()];
  return all.find((s) => isListEndpoint(s.url))
    || all.find((s) => isQueryEndpoint(s.url))
    || all.find((s) => /\/order\//.test(s.url))
    || null;
}

export function attachCapture(session, shopId) {
  if (attached.get(shopId)) return;
  attached.set(shopId, true);
  session.webRequest.onBeforeSendHeaders({ urls: ['*://*.kwaixiaodian.com/*'] }, (d, cb) => {
    const u = d.url || '';
    if (u.includes('__NS_hxfalcon') && u.includes('/gateway/')) {
      const p = u.split('?')[0].replace('https://s.kwaixiaodian.com', '');
      if (!candidates.has(shopId)) candidates.set(shopId, new Map());
      candidates.get(shopId).set(p, {
        url: u, path: p, method: d.method,
        headers: { ...d.requestHeaders }, captured_at: Date.now(),
      });
    }
    cb({ requestHeaders: d.requestHeaders });
  });
}

function signFile(shopId) { return path.join(shopDir(shopId), 'sign.json'); }

export function saveSign(shopId, sign) {
  try { fs.writeFileSync(signFile(shopId), JSON.stringify(sign), 'utf8'); } catch { /* ignore */ }
}

export function loadSign(shopId) {
  const s = pickSign(shopId);
  if (s && Date.now() - s.captured_at < TTL_MS) return s;
  // 内存里没有则读缓存（缓存也只接受列表接口）
  try {
    const c = JSON.parse(fs.readFileSync(signFile(shopId), 'utf8'));
    if (c && Date.now() - (c.captured_at || 0) < TTL_MS && isListEndpoint(c.url)) return c;
  } catch { /* ignore */ }
  return null;
}

/**
 * 确保拿到「列表接口」的可用签名。必要时在该店隐藏窗口里打开订单列表页触发请求。
 * @throws Error('NEED_LOGIN' | 'SIGN_TIMEOUT')
 */
export async function ensureSign({ shopId, session, win, log = () => {}, timeoutMs = 45000 }) {
  attachCapture(session, shopId);

  const cached = loadSign(shopId);
  if (cached) { log('[sign] 复用缓存签名'); return cached; }

  log('[sign] 打开订单列表页以获取签名…');
  try { await win.loadURL('https://s.kwaixiaodian.com/zone/order/list'); } catch { /* 允许重定向 */ }

  const t0 = Date.now();
  let fallback = null;
  while (Date.now() - t0 < timeoutMs) {
    await new Promise((r) => setTimeout(r, 800));

    // 被踢回登录页 → 需要重新扫码
    const url = win.webContents.getURL();
    if (/login\.kwaixiaodian|passport/.test(url)) throw new Error('NEED_LOGIN');

    const best = pickSign(shopId);
    if (best && isListEndpoint(best.url)) {
      saveSign(shopId, best);
      log(`[sign] 捕获列表接口签名 ${best.path}`);
      return best;
    }
    if (best && !fallback) fallback = best;
  }

  if (fallback) {
    log('[sign] 未等到列表接口签名，退回通用签名 ' + fallback.path);
    saveSign(shopId, fallback);
    return fallback;
  }
  throw new Error('SIGN_TIMEOUT');
}