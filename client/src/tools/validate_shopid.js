// 摸清登录后页面里的店铺标识来源，用于校准 readShopInfo 的提取规则
// 运行： node_modules\electron\dist\electron.exe src\tools\validate_shopid.js
import { app, session, BrowserWindow } from 'electron';
import fs from 'node:fs';
import path from 'node:path';

const PARTITION = 'persist:validate_import';
const log = (...a) => console.log('[id]', ...a);

app.whenReady().then(async () => {
  const ses = session.fromPartition(PARTITION);
  const win = new BrowserWindow({
    width: 1400, height: 900, show: false,
    webPreferences: { partition: PARTITION, contextIsolation: true, nodeIntegration: false },
  });
  win.webContents.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');

  try { await win.loadURL('https://s.kwaixiaodian.com/zone/order/list'); } catch { /* ignore */ }
  await new Promise((r) => setTimeout(r, 7000));

  const js = `(() => {
    const dump = { localStorage: {}, sessionStorage: {}, cookies: [], globals: [] };
    try { for (let i=0;i<localStorage.length;i++){ const k=localStorage.key(i); dump.localStorage[k]=String(localStorage.getItem(k)).slice(0,300); } } catch(e){}
    try { for (let i=0;i<sessionStorage.length;i++){ const k=sessionStorage.key(i); dump.sessionStorage[k]=String(sessionStorage.getItem(k)).slice(0,300); } } catch(e){}
    dump.cookies = (document.cookie||'').split(';').map(x=>x.trim().split('=')[0]);
    for (const g of ['__INITIAL_STATE__','__NUXT__','__DATA__','sellerInfo','shopInfo','userInfo']) {
      try { if (window[g] !== undefined) dump.globals.push([g, JSON.stringify(window[g]).slice(0,400)]); } catch(e){}
    }
    return dump;
  })()`;

  let dump = null;
  try { dump = await win.webContents.executeJavaScript(js); } catch (e) { log('读取失败', e.message); }

  const out = path.join(process.cwd(), 'probe-out', 'shopid-dump.json');
  fs.writeFileSync(out, JSON.stringify(dump, null, 2), 'utf8');
  log('已写入', out);

  if (dump) {
    log('localStorage 键:', Object.keys(dump.localStorage).join(', ') || '(空)');
    log('cookie 名:', dump.cookies.join(', '));
    // 试提取
    const hits = [];
    for (const [scope, obj] of [['ls', dump.localStorage], ['ss', dump.sessionStorage]]) {
      for (const [k, v] of Object.entries(obj)) {
        const m = String(v).match(/\b(\d{12,20})\b/);
        if (m) hits.push(`${scope}:${k} -> ${m[1]}`);
      }
    }
    log('疑似店铺ID命中:', hits.length ? hits.join(' | ') : '(无)');
  }

  try { win.destroy(); } catch { /* ignore */ }
  app.quit();
});