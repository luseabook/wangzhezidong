// 校准店铺名称来源
import { app, session, BrowserWindow } from 'electron';
import fs from 'node:fs';
const PARTITION = 'persist:validate_import';
app.whenReady().then(async () => {
  const win = new BrowserWindow({ width: 1400, height: 900, show: false, webPreferences: { partition: PARTITION, contextIsolation: true } });
  win.webContents.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
  try { await win.loadURL('https://s.kwaixiaodian.com/zone/order/list'); } catch {}
  await new Promise((r) => setTimeout(r, 6000));
  const js = `(() => {
    const r = { title: document.title, id: '', cands: [] };
    try { r.id = localStorage.getItem('seller-pc-userid') || ''; } catch(e){}
    const seen = new Set();
    for (const el of document.querySelectorAll('header *, [class*=header] *, [class*=Header] *, [class*=aside] *, [class*=sider] *')) {
      const t = (el.textContent || '').trim();
      if (!t || t.length < 2 || t.length > 24) continue;
      if (seen.has(t)) continue;
      const rect = el.getBoundingClientRect();
      if (rect.top > 260 || rect.left > 700) continue;
      seen.add(t);
      r.cands.push({ t, cls: (typeof el.className === 'string' ? el.className : '').slice(0, 60) });
      if (r.cands.length > 25) break;
    }
    return r;
  })()`;
  const out = await win.webContents.executeJavaScript(js).catch((e) => ({ error: e.message }));
  fs.writeFileSync('probe-out/nick.json', JSON.stringify(out, null, 2), 'utf8');
  console.log('title:', out.title);
  console.log('id:', out.id);
  console.log('候选文本:', (out.cands || []).map((c) => c.t).join(' | '));
  try { win.destroy(); } catch {}
  app.quit();
});