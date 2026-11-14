// 授权中心 · 数据层
// 用 node:sqlite（Node 22+ 内置），零依赖。
import { DatabaseSync } from 'node:sqlite';
import path from 'node:path';
import fs from 'node:fs';

export function openDb(dbPath) {
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  const db = new DatabaseSync(dbPath);
  db.exec(`
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS clients (
      id            INTEGER PRIMARY KEY AUTOINCREMENT,
      code          TEXT NOT NULL UNIQUE,          -- 激活码，发给客户
      name          TEXT NOT NULL,                 -- 客户名（你认得的名字）
      base_url      TEXT NOT NULL,                 -- 该客户的结算系统地址
      upload_token  TEXT NOT NULL,                 -- 客户端上传令牌
      slots         INTEGER NOT NULL DEFAULT 3,    -- 授权店铺位
      status        TEXT NOT NULL DEFAULT 'active',-- active|suspended|expired
      expires_at    TEXT,                          -- 授权到期日
      note          TEXT DEFAULT '',
      created_at    TEXT NOT NULL,
      updated_at    TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS devices (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id   INTEGER NOT NULL,
      machine_id  TEXT NOT NULL,
      version     TEXT DEFAULT '',
      ip          TEXT DEFAULT '',
      first_seen  TEXT NOT NULL,
      last_seen   TEXT NOT NULL,
      UNIQUE(client_id, machine_id)
    );

    CREATE TABLE IF NOT EXISTS bindings (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id  INTEGER NOT NULL,
      shop_id    TEXT NOT NULL,
      shop_nick  TEXT DEFAULT '',
      source     TEXT NOT NULL DEFAULT 'client',   -- client | report
      active     INTEGER NOT NULL DEFAULT 1,
      bound_at   TEXT NOT NULL,
      seen_at    TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_bind_client ON bindings(client_id, active);

    CREATE TABLE IF NOT EXISTS alerts (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id   INTEGER NOT NULL,
      type        TEXT NOT NULL,
      severity    TEXT NOT NULL,                   -- info|warn|high
      detail      TEXT NOT NULL,
      evidence    TEXT DEFAULT '',
      created_at  TEXT NOT NULL,
      acked       INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_alert_client ON alerts(client_id, acked);

    CREATE TABLE IF NOT EXISTS bind_attempts (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id  INTEGER NOT NULL,
      at         TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS switches (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id  INTEGER NOT NULL,
      shop_id    TEXT NOT NULL,
      at         TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_switch_client ON switches(client_id, at);

    CREATE TABLE IF NOT EXISTS events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id  INTEGER,
      kind       TEXT NOT NULL,
      detail     TEXT DEFAULT '',
      at         TEXT NOT NULL
    );
  `);
  return db;
}

const now = () => new Date().toISOString();

export function makeApi(db) {
  const q = (sql, ...p) => db.prepare(sql).all(...p);
  const one = (sql, ...p) => db.prepare(sql).get(...p);
  const run = (sql, ...p) => db.prepare(sql).run(...p);

  const api = {
    /** 原始查询（后台展示用；仅接受调用方硬编码的 SQL） */
    q(sql, ...p) { return q(sql, ...p); },

    // ---------- 客户 ----------
    clientByCode(code) {
      return one('SELECT * FROM clients WHERE code = ?', String(code || '').trim().toUpperCase());
    },
    clientById(id) { return one('SELECT * FROM clients WHERE id = ?', id); },
    listClients() {
      return q(`SELECT c.*,
        (SELECT COUNT(*) FROM bindings b WHERE b.client_id=c.id AND b.active=1) AS used,
        (SELECT COUNT(*) FROM devices d WHERE d.client_id=c.id) AS devices,
        (SELECT MAX(last_seen) FROM devices d WHERE d.client_id=c.id) AS last_seen,
        (SELECT COUNT(*) FROM alerts a WHERE a.client_id=c.id AND a.acked=0) AS open_alerts
        FROM clients c ORDER BY c.id DESC`);
    },
    createClient({ code, name, base_url, upload_token, slots = 3, expires_at = null, note = '' }) {
      run(`INSERT INTO clients(code,name,base_url,upload_token,slots,expires_at,note,created_at,updated_at)
           VALUES(?,?,?,?,?,?,?,?,?)`,
        String(code).trim().toUpperCase(), name, base_url, upload_token, Number(slots), expires_at, note, now(), now());
      return api.clientByCode(code);
    },
    updateClient(id, patch) {
      const c = api.clientById(id);
      if (!c) return null;
      const f = { ...c, ...patch };
      run(`UPDATE clients SET name=?, base_url=?, upload_token=?, slots=?, status=?, expires_at=?, note=?, updated_at=? WHERE id=?`,
        f.name, f.base_url, f.upload_token, Number(f.slots), f.status, f.expires_at, f.note || '', now(), id);
      return api.clientById(id);
    },

    // ---------- 占用 ----------
    used(clientId) {
      return one('SELECT COUNT(*) AS n FROM bindings WHERE client_id=? AND active=1', clientId).n;
    },
    activeShops(clientId) {
      return q('SELECT shop_id, shop_nick, bound_at, source FROM bindings WHERE client_id=? AND active=1 ORDER BY bound_at', clientId);
    },
    isBound(clientId, shopId) {
      return !!one('SELECT id FROM bindings WHERE client_id=? AND shop_id=? AND active=1', clientId, String(shopId));
    },
    bind(clientId, shopId, nick, source = 'client') {
      const existing = one('SELECT * FROM bindings WHERE client_id=? AND shop_id=? ORDER BY active DESC', clientId, String(shopId));
      if (existing) {
        run('UPDATE bindings SET active=1, shop_nick=?, seen_at=?, source=? WHERE id=?', nick || existing.shop_nick, now(), source, existing.id);
        return { ok: true, reused: true };
      }
      run('INSERT INTO bindings(client_id,shop_id,shop_nick,source,active,bound_at,seen_at) VALUES(?,?,?,?,1,?,?)',
        clientId, String(shopId), nick || '', source, now(), now());
      return { ok: true, reused: false };
    },
    unbind(clientId, shopId) {
      const b = one('SELECT id FROM bindings WHERE client_id=? AND shop_id=? AND active=1', clientId, String(shopId));
      if (!b) return false;
      run('UPDATE bindings SET active=0, seen_at=? WHERE id=?', now(), b.id);
      api.recordSwitch(clientId, shopId);
      return true;
    },

    // ---------- 换店限制（B 方案下的防滥用）----------
    /** 今天已发生多少次解绑（= 换店次数） */
    switchesToday(clientId) {
      const dayStart = new Date();
      dayStart.setHours(0, 0, 0, 0);
      return one('SELECT COUNT(*) AS n FROM switches WHERE client_id=? AND at>=?',
        clientId, dayStart.toISOString()).n;
    },
    /** 最近一次换店时间（毫秒），无则 null */
    lastSwitchAt(clientId) {
      const r = one('SELECT at FROM switches WHERE client_id=? ORDER BY at DESC LIMIT 1', clientId);
      return r ? new Date(r.at).getTime() : null;
    },
    recordSwitch(clientId, shopId) {
      run('INSERT INTO switches(client_id, shop_id, at) VALUES(?,?,?)', clientId, String(shopId), now());
    },
    touchBinding(clientId, shopId) {
      run('UPDATE bindings SET seen_at=? WHERE client_id=? AND shop_id=? AND active=1', now(), clientId, String(shopId));
    },

    // ---------- 设备 ----------
    touchDevice(clientId, machineId, version, ip) {
      const d = one('SELECT * FROM devices WHERE client_id=? AND machine_id=?', clientId, machineId);
      if (d) {
        run('UPDATE devices SET last_seen=?, version=?, ip=? WHERE id=?', now(), version || d.version, ip || d.ip, d.id);
        return { created: false, count: one('SELECT COUNT(*) AS n FROM devices WHERE client_id=?', clientId).n };
      }
      run('INSERT INTO devices(client_id,machine_id,version,ip,first_seen,last_seen) VALUES(?,?,?,?,?,?)',
        clientId, machineId, version || '', ip || '', now(), now());
      return { created: true, count: one('SELECT COUNT(*) AS n FROM devices WHERE client_id=?', clientId).n };
    },

    // ---------- 告警 ----------
    alert(clientId, type, severity, detail, evidence = '') {
      // 同类型 30 分钟内去重，避免刷屏
      const recent = one(`SELECT id FROM alerts WHERE client_id=? AND type=? AND created_at > ?`,
        clientId, type, new Date(Date.now() - 30 * 60000).toISOString());
      if (recent) return recent.id;
      const r = run('INSERT INTO alerts(client_id,type,severity,detail,evidence,created_at) VALUES(?,?,?,?,?,?)',
        clientId, type, severity, detail, typeof evidence === 'string' ? evidence : JSON.stringify(evidence), now());
      return Number(r.lastInsertRowid);
    },
    listAlerts({ onlyOpen = false, limit = 200 } = {}) {
      return q(`SELECT a.*, c.name AS client_name, c.code AS client_code
                FROM alerts a LEFT JOIN clients c ON c.id=a.client_id
                ${onlyOpen ? 'WHERE a.acked=0' : ''}
                ORDER BY a.id DESC LIMIT ?`, limit);
    },
    ackAlert(id) { run('UPDATE alerts SET acked=1 WHERE id=?', id); },

    log(clientId, kind, detail = '') {
      run('INSERT INTO events(client_id,kind,detail,at) VALUES(?,?,?,?)', clientId, kind, typeof detail === 'string' ? detail : JSON.stringify(detail), now());
    },
    recentEvents(limit = 100) { return q('SELECT * FROM events ORDER BY id DESC LIMIT ?', limit); },

    bindAttemptsRecent(clientId, minutes = 10) {
      const since = new Date(Date.now() - minutes * 60000).toISOString();
      return one('SELECT COUNT(*) AS n FROM bind_attempts WHERE client_id=? AND at>?', clientId, since).n;
    },
    recordBindAttempt(clientId) { run('INSERT INTO bind_attempts(client_id,at) VALUES(?,?)', clientId, now()); },

    // ---------- 授权状态 ----------
    effectiveStatus(c) {
      if (c.status === 'suspended') return 'suspended';
      if (c.expires_at) {
        const t = new Date(`${c.expires_at}T23:59:59+08:00`).getTime();
        if (Date.now() > t + 7 * 86400000) return 'expired';   // 到期后宽限 7 天
        if (Date.now() > t) return 'grace';
      }
      return 'active';
    },
  };
  return api;
}