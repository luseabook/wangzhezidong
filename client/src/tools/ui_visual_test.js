// 临时 UI 走查：用 mock IPC 加载三个客户端界面，截图检查布局。
// 运行：electron.exe src/tools/ui_visual_test.js <tmpRoot> <outDir>
import { app, BrowserWindow, ipcMain } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

const TMP = process.argv[2] || path.join(os.tmpdir(), `ks-ui-test-${Date.now()}`);
const OUT = process.argv[3] || path.join(process.cwd(), 'probe-out', 'ui-visual');
const UI = path.join(process.cwd(), 'src', 'ui');
fs.mkdirSync(TMP, { recursive: true });
fs.mkdirSync(OUT, { recursive: true });
app.setPath('userData', path.join(TMP, 'userData'));
app.on('window-all-closed', () => { /* 保持测试进程，继续截下一张 */ });

ipcMain.handle('shops:list', () => ({
  shops: [{
    id: '140000122179234', nick: '2号店 罗伯特', enabled: true,
    last_status: 'ok', last_sync_at: new Date(Date.now() - 60_000).toISOString(),
    stats: { today: 1183, last_run_rows: 1188 }, busy: false,
  }],
  lastRun: new Date(Date.now() - 60_000).toISOString(),
  needs: {}, interval: 60,
  license: { name: '桌面客户端测试', slots: 2, used: 0, expires_at: null, grace: null },
}));
ipcMain.handle('license:info', () => ({ license: null, server: 'http://43.139.84.147/lic' }));
ipcMain.handle('shops:action', () => true);
ipcMain.handle('shops:syncAll', () => true);
ipcMain.handle('shops:add', () => true);
ipcMain.handle('app:openData', () => true);
ipcMain.handle('app:info', () => ({ version: '1.0.0', dataRoot: TMP, configPath: path.join(TMP, 'config.json') }));
ipcMain.handle('app:autoStart:get', () => ({ enabled: true, packaged: false }));
ipcMain.handle('app:autoStart:set', (_e, enabled) => ({ enabled: !!enabled, packaged: false }));
const demoUpdate = process.env.DEMO_UPDATE === 'available'
  ? { status: 'available', currentVersion: '1.0.0', availableVersion: '1.0.1', progress: 0, error: '', feedConfigured: true, channel: 'latest' }
  : { status: 'up_to_date', currentVersion: '1.0.0', availableVersion: '', progress: 0, error: '', feedConfigured: true, channel: 'latest' };
ipcMain.handle('update:status', () => demoUpdate);
ipcMain.handle('update:check', () => demoUpdate);
ipcMain.handle('update:download', () => true);
ipcMain.handle('update:install', () => true);

function snap(file, width, height, html) {
  return new Promise((resolve, reject) => {
    const win = new BrowserWindow({
      width, height, show: false, autoHideMenuBar: true,
      webPreferences: { nodeIntegration: true, contextIsolation: false },
    });
    win.loadFile(path.join(UI, html));
    win.webContents.once('did-finish-load', async () => {
      await new Promise((r) => setTimeout(r, 900));
      try {
        const img = await win.capturePage();
        fs.writeFileSync(path.join(OUT, file), img.toPNG());
        win.destroy();
        resolve();
      } catch (e) { reject(e); }
    });
  });
}

app.whenReady().then(async () => {
  try {
    await snap('client-panel.png', 880, 620, 'shops.html');
    await snap('client-activate.png', 420, 330, 'activate.html');
    await snap('client-bind.png', 400, 480, 'bind.html');
    console.log('UI_SNAPSHOTS', OUT);
  } catch (e) {
    console.error('UI_TEST_FAILED', e);
  } finally {
    app.exit(0);
  }
});
