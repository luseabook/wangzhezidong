// 客户端授权流程测试（无 GUI）
// 运行： node src/tools/test_license.mjs [baseUrl] [adminPassword]
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { setRoot, loadConfig, saveConfig } from '../core/config.js';
import { activate, bindShop, unbindShop, heartbeat, localLicenseOk, machineId, reasonText } from '../core/license.js';

const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PWD = process.argv[3] || 'test-pass';

let pass = 0, fail = 0;
const ok = (m) => { console.log('  ✓', m); pass++; };
const no = (m) => { console.log('  ✗', m); fail++; };
const eq = (a, b, m) => (a === b ? ok(`${m} (${JSON.stringify(a)})`) : no(`${m}: 期望 ${JSON.stringify(b)} 实际 ${JSON.stringify(a)}`));

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'kslic-'));
setRoot(tmp);
const cfg0 = loadConfig();
cfg0.license_server = BASE;
saveConfig(cfg0);
console.log(`\n=== 客户端授权测试 ===\n本地配置: ${path.join(tmp, 'KsSync', 'config.json')}`);

// 服务端建客户
const login = await fetch(`${BASE}/admin/login`, {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: 'password=' + encodeURIComponent(PWD),
  redirect: 'manual',
});
const cookie = (login.headers.get('set-cookie') || '').split(';')[0];
if (!cookie) { console.log('✗ 无法登录授权中心'); process.exit(1); }

const code = 'KS-TEST-' + Math.random().toString(36).slice(2, 6).toUpperCase();
const created = await (await fetch(`${BASE}/admin/api/client`, {
  method: 'POST',
  headers: { 'content-type': 'application/json', cookie },
  body: JSON.stringify({ code, name: '客户端测试', base_url: 'http://43.139.84.147', slots: 2, upload_token: 'tok-from-center' }),
})).json();
eq(created?.ok, true, '服务端建客户');

// 1. 激活
const act = await activate(code, '0.1.0');
eq(!!act.license, true, '激活返回许可');
const c1 = loadConfig();
eq(c1.license.code, code, '激活码写入本地');
eq(c1.upload.base_url, 'http://43.139.84.147', '结算地址由授权中心下发');
eq(c1.upload.token, 'tok-from-center', '上传令牌由授权中心下发');
eq(c1.license.slots, 2, '店铺位写入本地');
eq(!!machineId(loadConfig()), true, '机器标识已生成且稳定');

// 2. 激活后再激活一次（幂等）
const act2 = await activate(code, '0.1.0');
eq(act2.license.used, act.license.used, '重复激活不改变占用');

// 3. 绑店
const b1 = await bindShop('140000122179234', '2号店');
eq(b1.ok, true, '绑第 1 个店');
const b2 = await bindShop('222222222222', '测试2号');
eq(b2.ok, true, '绑第 2 个店');
const b3 = await bindShop('333333333333', '测试3号');
eq(b3.ok, false, '第 3 个被拒');
eq(b3.reason, 'SLOTS_FULL', '拒绝原因=店铺位已满');
eq(!!reasonText(b3.reason), true, `客户看到的提示: ${reasonText(b3.reason)}`);

// 4. 解绑腾位
const u = await unbindShop('140000122179234');
eq(u.ok, true, '解绑成功');
const b3b = await bindShop('333333333333', '测试3号');
eq(b3b.ok, true, '解绑后第 3 个可绑');

// 5. 心跳
const hb = await heartbeat(['222222222222', '333333333333']);
eq(hb?.ok, true, '心跳上报成功');
eq(loadConfig().license.used, 2, '心跳回写占用数');

// 6. 过期判断（本地，不依赖网络）
const c2 = loadConfig();
c2.license.expires_at = '2020-01-01';
saveConfig(c2);
const expired = localLicenseOk(loadConfig());
eq(expired.ok, false, '过期后本地许可不可用');
eq(expired.reason, 'EXPIRED', '过期原因正确');

// 7. 停用判断
const c3 = loadConfig();
c3.license.expires_at = null;
c3.license.status = 'suspended';
saveConfig(c3);
const susp = localLicenseOk(loadConfig());
eq(susp.ok, false, '停用后本地许可不可用');
eq(susp.reason, 'SUSPENDED', '停用原因正确');

console.log(`\n=== 通过 ${pass} / 失败 ${fail} ===\n`);
try { fs.rmSync(tmp, { recursive: true, force: true }); } catch { /* ignore */ }
process.exit(fail ? 1 : 0);