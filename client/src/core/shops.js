// 店铺注册表：%APPDATA%\KsSync\shops.json
// 每店：独立 partition + 独立数据目录 + 独立状态
import fs from 'node:fs';
import path from 'node:path';
import { dataRoot } from './config.js';

const FILE = () => path.join(dataRoot(), 'shops.json');

function read() {
  try { return JSON.parse(fs.readFileSync(FILE(), 'utf8')); } catch { return { shops: [] }; }
}
function write(db) {
  fs.mkdirSync(dataRoot(), { recursive: true });
  const tmp = FILE() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(db, null, 2), 'utf8');
  fs.renameSync(tmp, FILE());
}

export function partitionOf(shopId) { return `persist:shop_${shopId}`; }
export function shopDir(shopId) {
  const d = path.join(dataRoot(), 'shops', String(shopId));
  fs.mkdirSync(d, { recursive: true });
  return d;
}

export function listShops() { return read().shops || []; }

export function getShop(id) {
  return listShops().find((s) => String(s.id) === String(id)) || null;
}

/** 新增或更新店铺（以 sellerId 为唯一键） */
export function upsertShop({ id, nick, extra = {} }) {
  const db = read();
  db.shops = db.shops || [];
  const sid = String(id);
  let s = db.shops.find((x) => String(x.id) === sid);
  if (!s) {
    s = {
      id: sid, nick: nick || sid, enabled: true,
      added_at: new Date().toISOString(),
      last_sync_at: '', last_status: 'new', last_error: '',
      stats: { today: 0, last_run_rows: 0 },
    };
    db.shops.push(s);
  } else {
    if (nick) s.nick = nick;
    Object.assign(s, extra);
  }
  write(db);
  return s;
}

export function updateShop(id, patch) {
  const db = read();
  const s = (db.shops || []).find((x) => String(x.id) === String(id));
  if (!s) return null;
  Object.assign(s, patch);
  write(db);
  return s;
}

/** 停用（不删数据、不删历史） */
export function disableShop(id) { return updateShop(id, { enabled: false, last_status: 'disabled' }); }
export function enableShop(id) { return updateShop(id, { enabled: true, last_status: 'idle' }); }

/** 移除：归档本地数据，不物理删除历史 */
export function removeShop(id) {
  const db = read();
  const s = (db.shops || []).find((x) => String(x.id) === String(id));
  if (!s) return false;
  db.shops = db.shops.filter((x) => String(x.id) !== String(id));
  write(db);
  try {
    const src = path.join(dataRoot(), 'shops', String(id));
    if (fs.existsSync(src)) {
      const arch = path.join(dataRoot(), 'archive', `${id}_${Date.now()}`);
      fs.mkdirSync(path.dirname(arch), { recursive: true });
      fs.renameSync(src, arch);
    }
  } catch { /* ignore */ }
  return true;
}

export function activeShops() { return listShops().filter((s) => s.enabled); }

// 每店本地库：按日期分片，避免单个大 JSON 随天数膨胀
// 目录：shops\<id>\orders\<YYYY-MM-DD>.json
export function ordersDir(shopId) {
  const d = path.join(shopDir(shopId), 'orders');
  fs.mkdirSync(d, { recursive: true });
  return d;
}
function shardFile(shopId, date) { return path.join(ordersDir(shopId), `${date}.json`); }

function readJson(p, fb) { try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch { return fb; } }
function writeJson(p, v) {
  const tmp = p + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(v), 'utf8');
  fs.renameSync(tmp, p);
}

export function loadShard(shopId, date) { return readJson(shardFile(shopId, date), {}); }

/** 幂等 upsert（按 oid + content_hash），仅写回有变化的日期分片 */
export function upsertOrders(shopId, records) {
  const byDate = new Map();
  for (const r of records) {
    const d = (r.create_time || '').slice(0, 10);
    if (!d) continue;
    if (!byDate.has(d)) byDate.set(d, []);
    byDate.get(d).push(r);
  }

  let inserted = 0, updated = 0, unchanged = 0;
  const changedDates = [];

  for (const [date, recs] of byDate) {
    const shard = loadShard(shopId, date);
    let dirty = false;
    for (const r of recs) {
      const cur = shard[r.oid];
      if (!cur) { shard[r.oid] = r; inserted++; dirty = true; }
      else if (cur.content_hash === r.content_hash) { unchanged++; }
      else { shard[r.oid] = r; updated++; dirty = true; }
    }
    if (dirty) {
      writeJson(shardFile(shopId, date), shard);
      changedDates.push(date);
    }
  }
  return { inserted, updated, unchanged, changedDates };
}

export function ordersByDate(shopId, date) {
  return Object.values(loadShard(shopId, date));
}

export function knownDates(shopId) {
  try {
    return fs.readdirSync(ordersDir(shopId))
      .filter((f) => /^\d{4}-\d{2}-\d{2}\.json$/.test(f))
      .map((f) => f.slice(0, 10));
  } catch { return []; }
}

export function recordRun(shopId, run) {
  const p = path.join(shopDir(shopId), 'runs.json');
  const list = readJson(p, []);
  list.push({ ...run, at: new Date().toISOString() });
  writeJson(p, list.slice(-200));
}

export function recentRuns(shopId, n = 20) {
  return readJson(path.join(shopDir(shopId), 'runs.json'), []).slice(-n);
}

/** 库里还存在非终态订单的最早日期，用于增量补刷（只扫最近 N 天分片） */
export function nonTerminalOldest(shopId, withinDays = 15) {
  const limit = Date.now() - withinDays * 86400000;
  let lo = null;
  for (const date of knownDates(shopId)) {
    const t = new Date(`${date}T00:00:00+08:00`).getTime();
    if (t < limit) continue;
    const shard = loadShard(shopId, date);
    for (const r of Object.values(shard)) {
      if (r.terminal) continue;
      if (lo === null || t < lo) lo = t;
      break;
    }
  }
  return lo;
}