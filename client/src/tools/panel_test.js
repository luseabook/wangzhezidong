// 临时面板联调：用隔离的 appData/userData 启动真实 main.js，
// 显示“快手订单自动同步”面板，点击“立即同步全部”，并截取面板状态。
// 运行：electron.exe src/tools/panel_test.js <tmpRoot>
import { app, session, BrowserWindow } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

const TMP = process.argv[2] || path.join(os.tmpdir(), `ks-panel-test-${Date.now()}`);
const SHOP_ID = '140000122179234';
const PARTITION = 'persist:validate_import';
const SOURCE_CONFIG = path.join(process.env.APPDATA || '', 'KsSync', 'config.json');
const SESSION_FILE = path.join(process.cwd(), 'probe-out', 'chrome-session.json');
const ROAMING = path.join(TMP, 'Roaming');
const KD = path.join(ROAMING, 'KsSync');

fs.mkdirSync(KD, { recursive: true });
fs.copyFileSync(SOURCE_CONFIG, path.join(KD, 'config.json'));
const cfg = JSON.parse(fs.readFileSync(path.join(KD, 'config.json'), 'utf8'));
cfg.sync = cfg.sync || {};
cfg.sync.force_dates = ['2026-08-28'];
cfg.sync.detail_max_per_run = 20;
cfg.export = cfg.export || {};
cfg.export.only_distributor = false;
fs.writeFileSync(path.join(KD, 'config.json'), JSON.stringify(cfg, null, 2));
fs.writeFileSync(path.join(KD, 'shops.json'), JSON.stringify({
  shops: [{
    id: SHOP_ID, nick: '2号店  罗伯特', enabled: true, partition: PARTITION,
    added_at: new Date().toISOString(), last_sync_at: '', last_status: 'idle',
    last_error: '', stats: { today: 0, last_run_rows: 0 },
  }],
}, null, 2));

app.setPath('appData', ROAMING);
app.setPath('userData', path.join(TMP, 'userData'));

const log = (...args) => console.log('[panel-test]', ...args.map((x) => typeof x === 'string' ? x : JSON.stringify(x)));

(async () => {
  await import('../main.js');
  app.whenReady().then(async () => {
    try {
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
      log(`面板数据目录=${KD} cookies=${cookies}`);

      let win = null;
      for (let i = 0; i < 60 && !win; i++) {
        win = BrowserWindow.getAllWindows().find((w) => w.getTitle() === '快手订单自动同步') || null;
        if (!win) await new Promise((r) => setTimeout(r, 250));
      }
      if (!win) throw new Error('未找到面板窗口');
      win.show();
      win.focus();
      await new Promise((r) => setTimeout(r, 1200));
      const before = await win.capturePage();
      fs.writeFileSync(path.join(TMP, 'panel-before.png'), before.toPNG());

      let sum = '';
      let list = '';
      if (process.env.PANEL_NO_SYNC !== '1') {
        const out = await win.webContents.executeJavaScript('syncAll(); "clicked"');
        log('点击立即同步全部:', out);

        for (let i = 0; i < 120; i++) {
          await new Promise((r) => setTimeout(r, 3000));
          try {
            sum = await win.webContents.executeJavaScript("document.getElementById('statLast').textContent");
            list = await win.webContents.executeJavaScript("document.getElementById('shopList').innerText");
          } catch { /* ignore */ }
          if (!/^—$/.test(sum.trim())) break;
        }
        await new Promise((r) => setTimeout(r, 1000));
      } else {
        sum = await win.webContents.executeJavaScript("document.getElementById('statLast').textContent");
        list = await win.webContents.executeJavaScript("document.getElementById('shopList').innerText");
      }
      const after = await win.capturePage();
      fs.writeFileSync(path.join(TMP, 'panel-after.png'), after.toPNG());
      fs.writeFileSync(path.join(TMP, 'panel-result.json'), JSON.stringify({ sum, list }, null, 2));
      log('面板摘要:', sum);
      log('面板店铺:', list.split('\n').slice(0, 5).join(' | '));
    } catch (e) {
      log('FAILED', e?.stack || e?.message || String(e));
    } finally {
      app.exit(0);
    }
  });
})();
