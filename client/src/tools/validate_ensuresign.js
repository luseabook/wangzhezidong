// 验证 ensureSign：模拟 runOne 的真实路径（开隐藏窗口 → 页面自己发请求 → 抓签名 → 取数）
// 运行： node_modules\electron\dist\electron.exe src\tools\validate_ensuresign.js
import { app, session, BrowserWindow } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { setRoot, loadConfig } from '../core/config.js';
import { upsertShop } from '../core/shops.js';
import { ensureSign } from '../core/signer.js';
import { Transport } from '../core/transport.js';

const SHOP_ID = '140000122179234';
const PARTITION = 'persist:validate_import';
const log = (...a) => console.log('[es]', ...a);

app.whenReady().then(async () => {
  setRoot(app.getPath('appData'));
  const cfg = loadConfig();
  const shop = upsertShop({ id: SHOP_ID, nick: '2号店  罗伯特', extra: { partition: PARTITION } });

  const ses = session.fromPartition(PARTITION);
  const cookies = await ses.cookies.get({ domain: 'kwaixiaodian.com' });
  log(`partition 内 cookie: ${cookies.length} 个`);

  const win = new BrowserWindow({
    width: 1280, height: 860, show: false,
    webPreferences: { partition: PARTITION, contextIsolation: true, nodeIntegration: false },
  });
  win.webContents.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');

  const result = { cookieCount: cookies.length, ok: false };
  try {
    const sign = await ensureSign({ shopId: shop.id, session: ses, win, log });
    result.signOk = true;
    result.signPath = sign.url.split('?')[0];

    const t = new Transport(cfg, ses, sign, { log });
    const d = new Date();
    const p = (x) => String(x).padStart(2, '0');
    const day = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
    const start = new Date(`${day}T00:00:00+08:00`).getTime();
    const end = new Date(`${day}T23:59:59+08:00`).getTime();
    const data = await t.fetchPage({ startMs: start, endMs: end, current: 1, status: '0' });
    result.fetchOk = true;
    result.today = day;
    result.total = data.total;
    result.ok = true;
    log(`取数成功 total=${data.total}`);
  } catch (e) {
    result.error = `${e.name}: ${e.message}`;
    log('失败:', result.error);
  }

  fs.writeFileSync(path.join(process.cwd(), 'probe-out', 'ensuresign-result.json'), JSON.stringify(result, null, 2), 'utf8');
  try { win.destroy(); } catch { /* ignore */ }
  app.quit();
});