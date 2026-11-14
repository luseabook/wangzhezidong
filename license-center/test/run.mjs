// 授权中心自动化测试（对着运行中的服务打）
// 运行： node test/run.mjs [baseUrl] [adminPassword]
const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PWD = process.argv[3] || process.env.ADMIN_PASSWORD || 'ks-admin-2026';

let pass = 0, fail = 0;
const ok = (m) => { console.log('  ✓', m); pass++; };
const no = (m) => { console.log('  ✗', m); fail++; };
const eq = (a, b, m) => (a === b ? ok(`${m} (${a})`) : no(`${m}: 期望 ${b} 实际 ${a}`));

let cookie = '';
async function post(p, body, withCookie = false) {
  const r = await fetch(BASE + p, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      ...(withCookie ? { cookie } : {}),
    },
    body: JSON.stringify(body || {}),
  });
  return { status: r.status, json: await r.json().catch(() => null), raw: r };
}
async function get(p, withCookie = false) {
  const r = await fetch(BASE + p, { headers: withCookie ? { cookie } : {} });
  return { status: r.status, json: await r.json().catch(() => null) };
}

const main = async () => {
  console.log('\n=== 授权中心测试 ===\n');

  // 0. 健康
  const h = await get('/health');
  eq(h.status, 200, '健康检查');

  // 1. 后台登录
  const lr = await fetch(`${BASE}/admin/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'password=' + encodeURIComponent(PWD),
    redirect: 'manual',
  });
  cookie = (lr.headers.get('set-cookie') || '').split(';')[0];
  eq(!!cookie && cookie.startsWith('ks_admin='), true, '后台登录拿到会话');

  const bad = await fetch(`${BASE}/admin/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: 'password=wrong', redirect: 'manual',
  });
  eq(bad.status, 401, '错误密码被拒');

  // 2. 建客户：2 个店铺位
  const code = 'TEST-' + Date.now().toString(36).toUpperCase();
  const created = await post('/admin/api/client', {
    code, name: '测试客户', base_url: 'http://127.0.0.1:1', slots: 2, upload_token: 'tok-test',
  }, true);
  eq(created.json?.ok, true, '创建客户');
  const cid = created.json?.client?.id;

  // 3. 激活
  const act = await post('/api/activate', { code, machine_id: 'M1', version: '1.0.0' });
  eq(act.json?.ok, true, '激活成功');
  eq(act.json?.slots, 2, '下发店铺位');
  eq(act.json?.base_url, 'http://127.0.0.1:1', '下发结算地址');

  // 4. 无效码
  const badCode = await post('/api/activate', { code: 'NOPE', machine_id: 'M1' });
  eq(badCode.json?.reason, 'INVALID_CODE', '无效激活码被拒');

  // 5. 绑定到满
  const b1 = await post('/api/bind', { code, shop_id: '111111111', shop_nick: 'A', machine_id: 'M1' });
  eq(b1.json?.ok, true, '绑第 1 个店');
  eq(b1.json?.used, 1, '占用=1');

  const b1again = await post('/api/bind', { code, shop_id: '111111111', shop_nick: 'A', machine_id: 'M1' });
  eq(b1again.json?.exists, true, '重复绑同一店幂等');
  eq(b1again.json?.used, 1, '占用仍=1');

  const b2 = await post('/api/bind', { code, shop_id: '222222222', shop_nick: 'B', machine_id: 'M1' });
  eq(b2.json?.ok, true, '绑第 2 个店');

  const b3 = await post('/api/bind', { code, shop_id: '333333333', shop_nick: 'C', machine_id: 'M1' });
  eq(b3.json?.reason, 'SLOTS_FULL', '第 3 个被拒（店铺位已满）');
  eq(b3.json?.used, 2, '拒绝后占用仍=2');

  // 6. 解绑腾位 → 再绑
  const u1 = await post('/api/unbind', { code, shop_id: '111111111' });
  eq(u1.json?.used, 1, '解绑后占用=1');

  const b3again = await post('/api/bind', { code, shop_id: '333333333', shop_nick: 'C', machine_id: 'M1' });
  // 冷却期内会被拦（测试环境把冷却设为 0 才应通过）
  if (b3again.json?.reason === 'COOLDOWN') {
    ok(`冷却生效，需等待 ${b3again.json.retry_after}s`);
  } else {
    eq(b3again.json?.ok, true, '解绑后成功绑第 3 个店');
  }

  // 7. 心跳
  const hb = await post('/api/heartbeat', { code, machine_id: 'M1', version: '1.0.0', active_shops: ['222222222', '333333333'] });
  eq(hb.json?.ok, true, '心跳上报');

  // 8. 结算系统回传未授权店铺 → 应当告警 + 计入占用
  const rep = await post('/api/report-shops', { client_code: code, shop_ids: ['222222222', '444444444'] });
  eq(rep.json?.ok, true, '回传接口可用');
  eq(rep.json?.over, true, '回传后判定超限');

  // 9. 后台能看到告警
  const ov = await get('/admin/api/overview', true);
  eq(ov.json?.ok, true, '后台总览可读');
  const hasHigh = (ov.json?.alerts || []).some((a) => a.client_id === cid && a.severity === 'high');
  eq(hasHigh, true, '超配产生 high 告警');

  // 10. 到期/停用
  await post('/admin/api/client', { id: cid, code, name: '测试客户', base_url: 'http://127.0.0.1:1', slots: 2, status: 'suspended', upload_token: 'tok-test' }, true);
  const act2 = await post('/api/activate', { code, machine_id: 'M1' });
  eq(act2.json?.ok, false, '停用后激活被拒');

  const lic = await get(`/api/license?code=${code}`);
  eq(lic.json?.status, 'suspended', 'license 返回停用状态');

  console.log(`\n=== 通过 ${pass} / 失败 ${fail} ===\n`);
  process.exit(fail ? 1 : 0);
};

main().catch((e) => { console.error('测试异常:', e); process.exit(1); });