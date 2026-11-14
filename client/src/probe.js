// 关键可行性验证（探针）
// 回答四个问题：
//   1) Electron 独立 session partition 能否打开快手后台
//   2) 能否把二维码截图出来（客户端要在自己窗口里画码，不能露浏览器）
//   3) 能否截获带 __NS_hxfalcon 的已签名请求
//   4) 能否用该 session 在该窗口之外（net 模块）复用签名取到订单 JSON
//
// 运行： npm run probe

const { app, BrowserWindow, session, net } = require('electron');
const fs = require('fs');
const path = require('path');

const PARTITION = 'persist:probe';
const OUT = path.join(__dirname, '..', 'probe-out');
const log = (...a) => console.log('[probe]', ...a);

fs.mkdirSync(OUT, { recursive: true });

let captured = null;

app.whenReady().then(async () => {
  const ses = session.fromPartition(PARTITION);

  // (3) 截获已签名请求
  ses.webRequest.onBeforeSendHeaders(
    { urls: ['*://*.kwaixiaodian.com/*'] },
    (details, cb) => {
      const u = details.url || '';
      if (!captured && u.includes('__NS_hxfalcon')) {
        captured = { url: u, method: details.method, headers: { ...details.requestHeaders } };
        log('已截获签名请求：', u.slice(0, 110) + '…');
      }
      cb({ requestHeaders: details.requestHeaders });
    }
  );

  const win = new BrowserWindow({
    width: 1440, height: 960,
    show: true,
    title: 'KsSync Probe',
    webPreferences: { partition: PARTITION, contextIsolation: true, nodeIntegration: false },
  });

  win.webContents.setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
  );

  log('打开登录页…');
  await win.loadURL('https://s.kwaixiaodian.com/');

  // (2) 循环截图，直到登录成功
  let loggedIn = false;
  for (let i = 0; i < 240; i++) {   // 最多 4 分钟
    await new Promise((r) => setTimeout(r, 1000));
    const url = win.webContents.getURL();
    if (i % 5 === 0) {
      try {
        const img = await win.webContents.capturePage();
        fs.writeFileSync(path.join(OUT, 'screen.png'), img.toPNG());
      } catch { /* ignore */ }
    }
    if (/s\.kwaixiaodian\.com\/zone/.test(url)) { loggedIn = true; log('登录成功：', url); break; }
  }

  if (!loggedIn) {
    log('未在 4 分钟内检测到登录，退出');
    fs.writeFileSync(path.join(OUT, 'result.json'), JSON.stringify({ loggedIn: false, captured: !!captured }, null, 2));
    app.quit();
    return;
  }

  // 等页面自己发出订单列表请求（列表页会调）
  try {
    await win.loadURL('https://s.kwaixiaodian.com/zone/order/list');
  } catch { /* 可能有重定向 */ }
  await new Promise((r) => setTimeout(r, 8000));

  const cookies = await ses.cookies.get({ domain: 'kwaixiaodian.com' });
  log('该 session 的 Cookie 数量：', cookies.length);

  // (4) 用 net 在该窗口之外复用签名取数
  let apiResult = null;
  if (captured) {
    const body = { orderSearchParam: { current: 1, pageSize: 10, status: '0', queryRange: '1', userType: 'domestic' } };
    apiResult = await new Promise((resolve) => {
      const req = net.request({ method: captured.method || 'POST', url: captured.url, session: ses, useSessionCookies: true });
      for (const [k, v] of Object.entries(captured.headers)) {
        if (/^(host|content-length|connection)$/i.test(k)) continue;
        try { req.setHeader(k, v); } catch { /* ignore */ }
      }
      req.setHeader('Content-Type', 'application/json;charset=UTF-8');
      let buf = '';
      req.on('response', (res) => {
        res.on('data', (d) => { buf += d.toString('utf8'); });
        res.on('end', () => {
          let json = null;
          try { json = JSON.parse(buf); } catch { /* ignore */ }
          resolve({ status: res.statusCode, bytes: buf.length, result: json?.result, total: json?.data?.total, listLen: json?.data?.orderList?.length, head: buf.slice(0, 180) });
        });
      });
      req.on('error', (e) => resolve({ error: e.message }));
      req.write(JSON.stringify(body));
      req.end();
    });
    log('net 直取结果：', JSON.stringify(apiResult));
  } else {
    log('未截获到签名请求——可能需要手动进入订单列表页');
  }

  const report = {
    loggedIn, partition: PARTITION, cookieCount: cookies.length,
    capturedSign: !!captured, signUrlPrefix: captured ? captured.url.split('?')[0] : null,
    apiResult,
    at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(OUT, 'result.json'), JSON.stringify(report, null, 2));
  log('报告已写入', path.join(OUT, 'result.json'));
  app.quit();
});