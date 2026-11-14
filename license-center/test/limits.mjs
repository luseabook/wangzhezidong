// 换店限制专项测试：验证冷却与每日上限
// 用法: node test/limits.mjs <baseUrl> <password> <expectedReason>
const BASE = process.argv[2] || 'http://127.0.0.1:8901';
const PWD = process.argv[3] || 'pw';
const EXPECT = process.argv[4] || 'COOLDOWN';

const j = async (r) => { try { return await r.json(); } catch { return null; } };

const main = async () => {
  // 登录拿会话
  const lr = await fetch(`${BASE}/admin/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'password=' + encodeURIComponent(PWD),
    redirect: 'manual',
  });
  const cookie = (lr.headers.get('set-cookie') || '').split(';')[0];
  if (!cookie) { console.log('✗ 登录失败'); process.exit(1); }

  const H = { 'content-type': 'application/json', cookie };
  const code = 'L' + Date.now().toString(36).toUpperCase();

  // 建客户：3 个位
  const c = await j(await fetch(`${BASE}/admin/api/client`, {
    method: 'POST', headers: H,
    body: JSON.stringify({ code, name: '限制测试', base_url: 'http://x', slots: 3, upload_token: 'tk' }),
  }));
  if (!c?.ok) { console.log('✗ 建客户失败', JSON.stringify(c)); process.exit(1); }

  const bind = async (shop) => j(await fetch(`${BASE}/api/bind`, {
    method: 'POST', headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ code, shop_id: shop, shop_nick: shop, machine_id: 'M' }),
  }));
  const unbind = async (shop) => j(await fetch(`${BASE}/api/unbind`, {
    method: 'POST', headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ code, shop_id: shop }),
  }));

  const a = await bind('200000001');   // 换店前先占一个
  const b = await bind('200000002');
  const u = await unbind('200000001');
  const r = await bind('200000003');   // 这一步应被限制拦住

  const okSetup = a?.ok && b?.ok && u?.ok;
  const hit = r?.reason === EXPECT;

  console.log(okSetup ? '  ✓ 前置步骤正常' : `  ✗ 前置步骤异常 ${JSON.stringify({a, b, u})}`);
  console.log(hit ? `  ✓ 命中限制: ${r.reason}${r.retry_after ? ' (需等待 ' + r.retry_after + 's)' : ''} — ${r.message || ''}`
                  : `  ✗ 期望 ${EXPECT}，实际 ${JSON.stringify(r)}`);
  process.exit(okSetup && hit ? 0 : 1);
};

main().catch((e) => { console.error('异常:', e.message); process.exit(1); });