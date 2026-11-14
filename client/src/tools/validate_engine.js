// 决定性验证：用导入的 Chrome 登录态，跑完整业务链（取数→解析→落库→产出→上传）
// 运行： node_modules\electron\dist\electron.exe src\tools\validate_engine.js [日期]
import { app, session } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { setRoot, loadConfig } from '../core/config.js';
import { upsertShop } from '../core/shops.js';
import { runShop } from '../core/engine.js';

const SESSION_FILE = path.join(process.cwd(), 'probe-out', 'chrome-session.json');
const DATE = process.argv[2] || '2026-08-28';
const SHOP_ID = '140000122179234';
const PARTITION = 'persist:validate_import';

const log = (...a) => { const s = a.map((x) => (typeof x === 'string' ? x : JSON.stringify(x))).join(' '); console.log('[v]', s); };
const mapSameSite = (s) => ({ Lax: 'lax', Strict: 'strict', None: 'no_restriction' }[s] || 'unspecified');

app.whenReady().then(async () => {
  setRoot(app.getPath('appData'));
  const cfg = loadConfig();
  const raw = JSON.parse(fs.readFileSync(SESSION_FILE, 'utf8'));
  const ses = session.fromPartition(PARTITION);

  let n = 0;
  for (const c of raw.cookies) {
    const domain = String(c.domain || '').replace(/^\./, '');
    try {
      await ses.cookies.set({
        url: `${c.secure ? 'https' : 'http'}://${domain}${c.path || '/'}`,
        name: c.name, value: c.value, domain: c.domain, path: c.path || '/',
        secure: !!c.secure, httpOnly: !!c.httpOnly,
        expirationDate: c.expires && c.expires > 0 ? c.expires : undefined,
        sameSite: mapSameSite(c.sameSite),
      });
      n++;
    } catch { /* ignore */ }
  }
  log(`cookie 注入 ${n} 个`);

  const shop = upsertShop({ id: SHOP_ID, nick: '2号店  罗伯特', extra: { partition: PARTITION } });
  log(`店铺就绪: ${shop.id} / ${shop.nick}`);

  const result = await runShop({
    shop, session: ses, win: null, cfg,
    dates: [DATE],
    signOverride: raw.sign,
    log,
  });

  const summary = {
    date: DATE,
    status: result.status,
    inserted: result.inserted, updated: result.updated, unchanged: result.unchanged,
    files: result.files.map((f) => path.basename(f)),
    uploads: result.uploads,
    errors: result.errors,
  };
  fs.writeFileSync(path.join(process.cwd(), 'probe-out', 'engine-result.json'), JSON.stringify(summary, null, 2), 'utf8');
  log('结果:', JSON.stringify(summary));
  app.quit();
});