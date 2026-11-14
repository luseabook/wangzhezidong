// 授权中心健康巡检：设备失联、open high 告警、到期风险、最低客户端版本。
// 运行：DB_PATH=/www/server/ks-license/data/license.db \
//       HEALTH_WEBHOOK=https://... node tools/healthcheck.mjs
import { DatabaseSync } from 'node:sqlite';

const DB_PATH = process.env.DB_PATH || '/www/server/ks-license/data/license.db';
const STALE_MINUTES = Math.max(5, Number(process.env.STALE_MINUTES || 90));
const MIN_VERSION = String(process.env.MIN_CLIENT_VERSION || '').trim();
const WEBHOOK = String(process.env.HEALTH_WEBHOOK || '').trim();
const TENANT = String(process.env.HEALTH_TENANT || 'license-center').trim();
const DRY_RUN = process.env.DRY_RUN === '1';

const db = new DatabaseSync(DB_PATH);
const now = Date.now();
const staleMs = STALE_MINUTES * 60 * 1000;
const issues = [];

const devices = db.prepare(`
  SELECT d.client_id, d.machine_id, d.version, d.last_seen, c.name AS client_name, c.status
  FROM devices d LEFT JOIN clients c ON c.id=d.client_id
`).all();

for (const d of devices) {
  const seen = d.last_seen ? new Date(d.last_seen).getTime() : 0;
  if (!seen || now - seen > staleMs) {
    issues.push({
      severity: 'high', code: 'device_stale',
      message: `${d.client_name || d.client_id} 设备心跳超过 ${STALE_MINUTES} 分钟`,
      client_id: d.client_id, machine_id: d.machine_id, last_seen: d.last_seen,
    });
  }
  if (MIN_VERSION && d.version && cmpVersion(d.version, MIN_VERSION) < 0) {
    issues.push({
      severity: 'warn', code: 'client_old',
      message: `${d.client_name || d.client_id} 客户端版本 ${d.version} 低于最低版本 ${MIN_VERSION}`,
      client_id: d.client_id, machine_id: d.machine_id, version: d.version,
    });
  }
}

const highAlerts = db.prepare(`SELECT COUNT(*) AS n FROM alerts WHERE acked=0 AND severity='high'`).get().n;
if (highAlerts > 0) {
  issues.push({ severity: 'high', code: 'open_high_alerts', message: `存在 ${highAlerts} 条未处理的 high 告警`, count: highAlerts });
}

const expiring = db.prepare(`
  SELECT id, name, expires_at FROM clients
  WHERE status='active' AND expires_at IS NOT NULL
    AND date(expires_at) <= date('now', '+30 day')
`).all();
for (const c of expiring) {
  issues.push({
    severity: 'warn', code: 'license_expiring',
    message: `${c.name} 授权将在 30 天内到期（${c.expires_at}）`,
    client_id: c.id, expires_at: c.expires_at,
  });
}

const payload = {
  tenant: TENANT,
  checked_at: new Date().toISOString(),
  devices: devices.length,
  open_high_alerts: highAlerts,
  issues,
};

console.log(JSON.stringify(payload, null, 2));

if (issues.length && WEBHOOK && !DRY_RUN) {
  const lines = [
    `【授权中心巡检】${TENANT}`,
    `时间：${payload.checked_at}`,
    `设备：${devices.length} · open high：${highAlerts}`,
    ...issues.slice(0, 20).map((i) => `[${i.severity}] ${i.message}`),
  ];
  try {
    const res = await fetch(WEBHOOK, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ msgtype: 'text', text: { content: lines.join('\n') } }),
      signal: AbortSignal.timeout(5000),
    });
    if (!res.ok) console.error('webhook HTTP', res.status);
  } catch (e) {
    console.error('webhook error', e?.message || e);
  }
}

process.exit(issues.some((i) => i.severity === 'high') ? 1 : 0);

function cmpVersion(a, b) {
  const pa = String(a).replace(/^v/i, '').split('.').map((x) => parseInt(x, 10) || 0);
  const pb = String(b).replace(/^v/i, '').split('.').map((x) => parseInt(x, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pa[i] || 0) - (pb[i] || 0);
    if (d) return d < 0 ? -1 : 1;
  }
  return 0;
}
