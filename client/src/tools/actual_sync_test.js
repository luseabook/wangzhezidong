// 临时联调脚本：用隔离的 userData 跑一轮真实采集 + 上传 + 轮询。
// 不修改客户的 %APPDATA%\KsSync，复制配置到临时目录后运行。
// 运行：electron.exe src/tools/actual_sync_test.js <tmpRoot> [date1,date2]
import { app, session, BrowserWindow } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { setRoot, loadConfig } from '../core/config.js';
import { upsertShop } from '../core/shops.js';
import { runShop } from '../core/engine.js';

const TMP = process.argv[2] || path.join(os.tmpdir(), `ks-sync-test-${Date.now()}`);
const DATES = (process.argv[3] || '2026-08-27').split(',').map((s) => s.trim()).filter(Boolean);
const SOURCE_CONFIG = path.join(process.env.APPDATA || '', 'KsSync', 'config.json');
const SESSION_FILE = path.join(process.cwd(), 'probe-out', 'chrome-session.json');
const SHOP_ID = '140000122179234';
const PARTITION = 'persist:validate_import';

fs.mkdirSync(TMP, { recursive: true });
app.setPath('userData', path.join(TMP, 'Electron'));
app.setPath('sessionData', path.join(TMP, 'Electron'));
setRoot(TMP);
fs.mkdirSync(path.join(TMP, 'KsSync'), { recursive: true });
fs.copyFileSync(SOURCE_CONFIG, path.join(TMP, 'KsSync', 'config.json'));

const log = (...args) => {
  const line = args.map((x) => (typeof x === 'string' ? x : JSON.stringify(x))).join(' ');
  console.log(`[actual-sync] ${line}`);
};

app.whenReady().then(async () => {
  const raw = JSON.parse(fs.readFileSync(SESSION_FILE, 'utf8'));
  const ses = session.fromPartition(PARTITION);
  const mapSameSite = (s) => ({ Lax: 'lax', Strict: 'strict', None: 'no_restriction' }[s] || 'unspecified');

  let cookies = 0;
  for (const c of raw.cookies || []) {
    const domain = String(c.domain || '').replace(/^\./, '');
    try {
      await ses.cookies.set({
        url: `${c.secure ? 'https' : 'http'}://${domain}${c.path || '/'}`,
        name: c.name, value: c.value, domain: c.domain, path: c.path || '/',
        secure: !!c.secure, httpOnly: !!c.httpOnly,
        expirationDate: c.expires && c.expires > 0 ? c.expires : undefined,
        sameSite: mapSameSite(c.sameSite),
      });
      cookies++;
    } catch { /* ignore */ }
  }
  log(`隔离目录=${TMP} cookies=${cookies} dates=${DATES.join(',')}`);

  const shop = upsertShop({ id: SHOP_ID, nick: '2号店  罗伯特', extra: { partition: PARTITION } });
  const cfg = loadConfig();
  cfg.export.only_distributor = false;
  cfg.sync.detail_max_per_run = 20;

  const win = new BrowserWindow({
    width: 1280, height: 860, show: false,
    webPreferences: { partition: PARTITION, contextIsolation: true, nodeIntegration: false },
  });
  win.webContents.setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
  );

  try {
    const result = await runShop({ shop, session: ses, win, cfg, dates: DATES, log });
    console.log(JSON.stringify({
      status: result.status,
      dates: result.dates,
      inserted: result.inserted,
      updated: result.updated,
      unchanged: result.unchanged,
      files: (result.files || []).map((f) => path.basename(f)),
      uploads: result.uploads,
      errors: result.errors,
      human: result.human || null,
    }, null, 2));
  } catch (e) {
    console.error('FATAL', e?.stack || e?.message || String(e));
  } finally {
    try { win.destroy(); } catch { /* ignore */ }
    app.quit();
  }
});
