// 验证：详情接口能否用会话直接调用（不依赖页面、不需要签名）
// 运行: npx electron src\tools\validate_detail.mjs
import { app, session, net } from 'electron';

const PARTITION = process.env.KS_BIND_PARTITION || 'persist:validate_import';
const OID = process.env.KS_OID || '2623902183458506';
const BASE = 'https://s.kwaixiaodian.com';

function post(ses, path, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = net.request({
      method: 'POST',
      url: BASE + path,
      session: ses,
      useSessionCookies: true,
    });
    req.setHeader('content-type', 'application/json');
    req.setHeader('accept', 'application/json');
    for (const [k, v] of Object.entries(headers)) req.setHeader(k, v);
    req.on('response', (res) => {
      let buf = '';
      res.on('data', (c) => (buf += c.toString()));
      res.on('end', () => resolve({ status: res.statusCode, body: buf }));
    });
    req.on('error', reject);
    req.write(JSON.stringify(body));
    req.end();
  });
}

app.whenReady().then(async () => {
  const ses = session.fromPartition(PARTITION);
  console.log('分区:', PARTITION);
  console.log('cookie 数:', (await ses.cookies.get({})).length);

  // 1) 详情接口（不带签名）
  const r = await post(ses, '/gateway/app/tts/ks/seller/order/detail/v2', { oid: OID });
  let j = null;
  try { j = JSON.parse(r.body); } catch { /* ignore */ }
  console.log('\n=== 详情接口（裸调，无签名）===');
  console.log('HTTP', r.status, 'result =', j?.result, 'err =', j?.error_msg || '');
  console.log('响应长度:', r.body.length);

  if (j?.result === 1) {
    // 提取关键字段
    const text = r.body;
    const grab = (id) => {
      const re = new RegExp(`"infoBarId":"${id}"[\\s\\S]{0,600}?"text":"([^"]*)"`);
      const m = text.match(re);
      return m ? m[1] : null;
    };
    console.log('\n=== 提取到的时间字段 ===');
    for (const id of ['createTimeDesc', 'payTimeDesc', 'shipmentTime', 'orderSuccessTimeDesc', 'orderId', 'buyerNick']) {
      console.log(`  ${id} = ${grab(id)}`);
    }
  } else {
    console.log('响应片段:', r.body.slice(0, 200));
  }

  app.quit();
});