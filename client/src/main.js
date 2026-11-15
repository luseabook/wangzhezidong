// Electron 主进程 —— 产品骨架
// 职责：窗口/托盘/调度/IPC，业务全部委托给 core/*
import { app, BrowserWindow, Tray, Menu, session, nativeImage, ipcMain, shell } from 'electron';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { setRoot, loadConfig, saveConfig, dataRoot, writeLog } from './core/config.js';
import { dotPng, COLORS } from './core/icon.js';
import * as shops from './core/shops.js';
import { runShop } from './core/engine.js';
import { NEEDS } from './core/messages.js';
import { initUpdater, updaterStatus, checkForUpdates, downloadUpdate, installUpdate, appVersion } from './core/updater.js';
import {
  activate, bindShop, unbindShop, heartbeat,
  localLicenseOk, graceInfo, reasonText, machineId,
} from './core/license.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const log = (...a) => writeLog('[main]', ...a);

if (!app.requestSingleInstanceLock()) {
  app.quit();               // 单实例：客户多点几次也只跑一个
} else {
  let cfg, tray, winShops, bindWin = null, winActivate = null;
  const busy = new Set();
  let lastRun = null;
  let binding = false;

  app.on('second-instance', () => showShops());
  app.on('window-all-closed', () => { /* 托盘常驻，不退出 */ });

  app.whenReady().then(() => {
    setRoot(app.getPath('appData'));
    cfg = loadConfig();
    log('启动，数据目录', dataRoot());

    createShopsWindow();
    createTray();
    registerIpc();
    applyAutoStart();
    initUpdater({ getConfig: () => cfg, log });

    const hidden = process.argv.includes('--hidden');
    // 首次使用：先激活，再扫码
    if (!localLicenseOk(cfg).ok) {
      ensureActivateWindow();
    } else if (shops.listShops().length === 0) {
      winShops.show();
      winShops.focus();
      setTimeout(() => startBind(), 1500);
    } else if (process.argv.includes('--bind')) {
      setTimeout(() => startBind(), 1200);
    } else if (!hidden) {
      setTimeout(() => showShops(), 800);
    }

    setTimeout(() => runAll('startup'), 8000);
    setInterval(() => runAll('timer'), Math.max(5, cfg.sync.interval_minutes) * 60000);
  });

  // ---------- 窗口 ----------
  function createShopsWindow() {
    winShops = new BrowserWindow({
      width: 880, height: 620, show: false, title: '快手订单自动同步', autoHideMenuBar: true,
      webPreferences: { nodeIntegration: true, contextIsolation: false },
    });
    winShops.loadFile(path.join(__dirname, 'ui', 'shops.html'));
    winShops.on('close', (e) => { if (!app.isQuitting) { e.preventDefault(); winShops.hide(); } });
  }
  function showShops() {
    if (!winShops || winShops.isDestroyed()) createShopsWindow();
    winShops.show(); winShops.focus();
  }

  function applyAutoStart() {
    // 开发模式/自动化测试不改系统登录项，避免把 electron.exe 注册进开机启动
    if (!app.isPackaged || process.env.KS_DISABLE_AUTOSTART === '1') return;
    try {
      app.setLoginItemSettings({
        openAtLogin: cfg.autostart !== false,
        path: process.execPath,
        args: ['--hidden'],
      });
    } catch (e) {
      log('设置开机自启失败', e?.message || String(e));
    }
  }

  // ---------- 激活 ----------
  function ensureActivateWindow() {
    if (winActivate && !winActivate.isDestroyed()) { winActivate.show(); winActivate.focus(); return; }
    winActivate = new BrowserWindow({
      width: 420, height: 330, show: true, resizable: false, maximizable: false,
      title: '激活', autoHideMenuBar: true,
      webPreferences: { nodeIntegration: true, contextIsolation: false },
    });
    winActivate.loadFile(path.join(__dirname, 'ui', 'activate.html'));
    winActivate.on('closed', () => { winActivate = null; });
  }

  // ---------- 托盘 ----------
  function createTray() {
    tray = new Tray(nativeImage.createFromBuffer(dotPng(32, COLORS.idle)));
    tray.setToolTip('快手订单自动同步');
    tray.on('click', showShops);
    refreshTray();
  }
  function refreshTray() {
    const list = shops.listShops();
    const active = list.filter((s) => s.enabled);
    const bad = active.filter((s) => s.last_status === 'error' || s.last_status === 'need_login');
    const color = bad.length ? COLORS.error : active.length ? COLORS.ok : COLORS.idle;
    try { tray.setImage(nativeImage.createFromBuffer(dotPng(32, color))); } catch { /* ignore */ }
    tray.setToolTip(`快手订单同步 · ${active.length} 个店铺${bad.length ? ` · ${bad.length} 个待处理` : ''}`);
    tray.setContextMenu(Menu.buildFromTemplate([
      { label: `${active.length} 个店铺在同步`, enabled: false },
      { type: 'separator' },
      { label: '打开面板', click: showShops },
      { label: '立即同步全部', click: () => runAll('manual') },
      { label: '全量回刷历史', click: () => runAll('manual-full', { fullRefresh: true }) },
      { label: '添加店铺', click: () => startBind() },
      { type: 'separator' },
      { label: '打开数据目录', click: () => shell.openPath(dataRoot()) },
      { label: '退出', click: () => { app.isQuitting = true; app.quit(); } },
    ]));
  }

  // ---------- IPC ----------
  function registerIpc() {
    ipcMain.handle('shops:list', () => ({
      shops: shops.listShops().map((s) => ({ ...s, busy: busy.has(s.id) })),
      lastRun, needs: NEEDS, interval: cfg.sync.interval_minutes,
      license: cfg.license ? {
        name: cfg.license.name, slots: cfg.license.slots, used: cfg.license.used,
        expires_at: cfg.license.expires_at, grace: graceInfo(cfg),
      } : null,
    }));

    // ---------- 授权 ----------
    ipcMain.handle('license:info', () => ({
      license: cfg.license || null,
      grace: graceInfo(cfg),
      server: cfg.license_server || '',
    }));

    ipcMain.handle('license:activate', async (_e, code) => {
      try {
        const r = await activate(code, app.getVersion());
        cfg = loadConfig();
        // 激活成功后，把本地已有店铺补登记到授权中心（避免本地有店、服务端却未计入占用）
        const migrated = [];
        for (const s of shops.listShops()) {
          const res = await bindShop(s.id, s.nick);
          migrated.push({ id: s.id, ok: !!res?.ok, reason: res?.reason });
        }
        cfg = loadConfig();
        setTimeout(() => checkForUpdates(), 3000);
        winActivate?.webContents.send('license:ok', { license: cfg.license, migrated });
        setTimeout(() => {
          try { winActivate?.close(); } catch { /* ignore */ }
          showShops();
          refreshTray();
          if (shops.listShops().length === 0) startBind();
        }, 900);
        return { ok: true, license: cfg.license, migrated };
      } catch (e) {
        return { ok: false, reason: e.reason || 'ERROR', message: e.message };
      }
    });
    ipcMain.handle('shops:action', async (_e, { id, action }) => {
      if (action === 'disable') shops.disableShop(id);
      if (action === 'enable') shops.enableShop(id);
      if (action === 'remove') {
        // 先在授权中心腾出店铺位，再归档本地数据
        await unbindShop(id).catch(() => null);
        shops.removeShop(id);
        cfg = loadConfig();
      }
      if (action === 'rescan') return startBind({ shopId: id });
      if (action === 'sync') runOne(id, 'manual');
      refreshTray();
      return true;
    });
    ipcMain.handle('shops:syncAll', () => { runAll('manual'); return true; });
    ipcMain.handle('shops:add', () => startBind());
    ipcMain.handle('app:openData', () => shell.openPath(dataRoot()));
    ipcMain.handle('app:info', () => ({
      version: appVersion(), dataRoot: dataRoot(),
      configPath: path.join(dataRoot(), 'config.json'),
    }));
    ipcMain.handle('app:autoStart:get', () => ({
      enabled: app.isPackaged ? !!app.getLoginItemSettings().openAtLogin : cfg.autostart !== false,
      packaged: app.isPackaged,
    }));
    ipcMain.handle('app:autoStart:set', (_e, enabled) => {
      cfg.autostart = !!enabled;
      saveConfig(cfg);
      applyAutoStart();
      return { enabled: app.isPackaged ? !!app.getLoginItemSettings().openAtLogin : cfg.autostart, packaged: app.isPackaged };
    });
    ipcMain.handle('update:status', () => updaterStatus());
    ipcMain.handle('update:check', () => checkForUpdates());
    ipcMain.handle('update:download', () => downloadUpdate());
    ipcMain.handle('update:install', () => installUpdate());
  }

  // ---------- 调度 ----------
  async function runOne(id, why = 'timer', { fullRefresh = false } = {}) {
    id = String(id);
    const shop = shops.getShop(id);
    if (!shop || busy.has(id)) return;
    busy.add(id); refreshTray();
    winShops?.webContents.send('shop:progress', { id, status: 'running' });

    let win = null;
    const partition = shop.partition || shops.partitionOf(shop.id);
    try {
      win = new BrowserWindow({
        width: 1280, height: 860, show: false,
        webPreferences: { partition, contextIsolation: true, nodeIntegration: false },
      });
      const ses = session.fromPartition(partition);
      const result = await runShop({ shop, session: ses, win, cfg, log, fullRefresh });
      winShops?.webContents.send('shop:progress', { id, status: result?.status || 'done', result });
    } catch (e) {
      log('runOne 异常', e?.message || String(e));
    } finally {
      try { win?.destroy(); } catch { /* ignore */ }
      busy.delete(id); refreshTray();
      winShops?.webContents.send('shop:changed');
    }
  }

  function fullRefreshDue() {
    const now = new Date();
    const hour = Number(cfg.sync.full_refresh_hour ?? 3);
    if (now.getHours() < hour) return false;

    const interval = Number(cfg.sync.full_refresh_interval_days || 1);
    const last = shops.getFullRefresh()?.at;
    if (!last) return true;

    return Date.now() - new Date(last).getTime() >= interval * 86400000;
  }

  let runningAll = false;
  async function runAll(why, { fullRefresh = null } = {}) {
    if (runningAll) return;
    runningAll = true;
    const full = fullRefresh ?? fullRefreshDue();
    const list = shops.activeShops();
    log(`本轮开始（${why}）${full ? '全量回刷' : '增量'}：${list.length} 个店铺`);
    for (const s of list) {
      await runOne(s.id, why, { fullRefresh: full });
      await new Promise((r) => setTimeout(r, 3000));   // 多店之间留间隔，模仿人工节奏
    }
    if (full) shops.markFullRefresh();
    lastRun = new Date().toISOString();
    runningAll = false; refreshTray();

    // 心跳：上报本机当前活跃店铺（授权中心据此判断失联与占用）
    try {
      await heartbeat(list.map((s) => s.id));
      cfg = loadConfig();
    } catch { /* 授权中心不可达不影响同步 */ }

    winShops?.webContents.send('shops:changed');
  }

  // ---------- 绑定：连续扫码 ----------
  async function startBind({ shopId = null } = {}) {
    if (binding) { try { bindWin?.focus(); } catch { /* ignore */ } return { ok: false, reason: 'busy' }; }
    binding = true;

    bindWin = new BrowserWindow({
      width: 400, height: 480, show: true, resizable: false, maximizable: false,
      title: '绑定店铺', autoHideMenuBar: true,
      webPreferences: { nodeIntegration: true, contextIsolation: false },
    });
    bindWin.loadFile(path.join(__dirname, 'ui', 'bind.html'));

    let closed = false;
    bindWin.on('closed', () => { closed = true; bindWin = null; });

    // 首次进入时允许指定已存在的 partition（自测/迁移已有登录态用）
    let partition = shopId
      ? (shops.getShop(shopId)?.partition || shops.partitionOf(shopId))
      : (process.env.KS_BIND_PARTITION || `persist:bind_${Date.now()}`);
    let index = 0;

    while (!closed) {
      const r = await bindOne({ partition, closed: () => closed });
      if (!r?.ok || closed) break;
      index++;
      partition = `persist:bind_${Date.now()}_${index}`;
      // 给客户 1.5 秒看到"已添加 xxx"，再自动进入下一个
      bindWin?.webContents.send('bind:next', index);
      await new Promise((res) => setTimeout(res, 1500));
    }

    binding = false;
    try { bindWin?.close(); } catch { /* ignore */ }
    bindWin = null;
    return { ok: true };
  }

  /** 一次扫码：返回 {ok:true, shop} 或 {ok:false} */
  function bindOne({ partition, closed }) {
    return new Promise((resolve) => {
      const ses = session.fromPartition(partition);
      const auth = new BrowserWindow({
        width: 1400, height: 940, show: false,
        webPreferences: { partition, contextIsolation: true, nodeIntegration: false },
      });
      auth.webContents.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
      );
      auth.loadURL('https://s.kwaixiaodian.com/').catch(() => {});

      let done = false;
      let tick = 0;
      const finish = (v) => {
        if (done) return;
        done = true;
        clearInterval(timer);
        setTimeout(() => { try { auth.destroy(); } catch { /* ignore */ } }, 400);
        resolve(v);
      };

      let forceQrRefresh = false;
    // 客户端点击二维码 → 强制刷新
    ipcMain.on('bind:refresh', () => { forceQrRefresh = true; });

    const timer = setInterval(async () => {
        if (closed() || auth.isDestroyed()) return finish({ ok: false });
        const url = auth.webContents.getURL();

        if (/s\.kwaixiaodian\.com\/zone/.test(url)) {
          clearInterval(timer);
          bindWin?.webContents.send('bind:status', '正在读取店铺信息…');
          const info = await readShopInfo(auth, partition);
          if (info?.id) {
            // 店铺位校验以授权中心为准：满了/冷却中/超每日上限都在这里被拒
            const quota = await bindShop(info.id, info.nick || '');
            if (!quota?.ok) {
              bindWin?.webContents.send('bind:status', quota?.message || reasonText(quota?.reason));
              finish({ ok: false, reason: quota?.reason });
              return;
            }

            // 向结算系统查询店铺显示名，并校验是否已登记
            const remote = await lookupShop(info.id);
            const nick = remote?.seller_nick || info.nick || info.id;
            const saved = shops.upsertShop({
              id: info.id, nick,
              extra: { partition, registered: !!remote?.ok },
            });
            bindWin?.webContents.send('bind:done', saved);
            if (!remote?.ok) {
              bindWin?.webContents.send('bind:status', '该店铺尚未在结算系统登记，请联系运营');
            }
            refreshTray();
            finish({ ok: true, shop: saved });
          } else {
            bindWin?.webContents.send('bind:status', '未能识别店铺信息，请重新扫码');
            finish({ ok: false });
          }
          return;
        }

        // 二维码过期处理：页面自己会在码上盖「已过期 / 刷新」，那只是截图里的一层像素，点不到。
        // 因此这里主动去点页面的刷新，并支持客户点我方窗口强制刷新。
        if (tick++ % 3 === 0 || forceQrRefresh) {
          const forced = forceQrRefresh;
          forceQrRefresh = false;
          const st = await clickPageRefresh(auth, forced);
          if (st?.clicked) {
            log(`[bind] 二维码刷新（${st.how}${forced ? '/手动' : ''}）`);
            bindWin?.webContents.send('bind:status', '正在刷新二维码…');
            await new Promise((r) => setTimeout(r, 1200));
          }
        }

        const rect = await findQrRect(auth.webContents);
        if (!rect) return;
        try {
          const img = await auth.webContents.capturePage(rect);
          bindWin?.webContents.send('bind:qr', img.toDataURL());
        } catch { /* ignore */ }
      }, 1200);
    });
  }

  async function findQrRect(wc) {
    const js = `(() => {
      const pick = (sel, any) => {
        for (const el of document.querySelectorAll(sel)) {
          const r = el.getBoundingClientRect();
          if (r.width >= 120 && r.height >= 120 && Math.abs(r.width - r.height) <= 24 && r.left >= 0 && r.top >= 0) {
            if (!any) {
              const hint = (typeof el.className === 'string' ? el.className : '') + ' ' + (el.alt || '') + ' ' + (el.id || '');
              if (!/qr|code/i.test(hint) && el.tagName !== 'CANVAS' && el.tagName !== 'IMG') continue;
            }
            return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
          }
        }
        return null;
      };
      return pick('canvas', false) || pick('img', false) || pick('div', false);
    })()`;
    return wc.executeJavaScript(js).catch(() => null);
  }

  /** 在页面里触发二维码刷新。
   *  页面过期后会在码上盖一层「二维码已过期 / 刷新」，那层是截图里的像素，点不到，
   *  所以必须在页面里派发点击。QR 容器本身也支持点一下刷新，作为兜底。
   */
  async function clickPageRefresh(auth, forced = false) {
    const js = `(() => {
      const out = { expired: false, clicked: false, how: '' };
      const txt = (document.body && document.body.innerText) || '';
      out.expired = /(二维码|扫码)[^。\\n]{0,8}(过期|失效|超时)/.test(txt);
      if (!out.expired && !${forced ? 'true' : 'false'}) return out;
      if (!out.expired) out.expired = true;
      const nodes = [...document.querySelectorAll('button,a,div,span,i')];
      const btn = nodes.find((e) => {
        const s = (e.textContent || '').trim();
        return s === '刷新' || s === '点击刷新' || s === '重新获取' || s === '刷新二维码';
      });
      if (btn) { btn.dispatchEvent(new MouseEvent('click', { bubbles: true })); out.clicked = true; out.how = 'text'; return out; }
      const qr = [...document.querySelectorAll('img,canvas')].find((e) => {
        const r = e.getBoundingClientRect();
        return r.width >= 120 && Math.abs(r.width - r.height) <= 24;
      });
      if (qr) { qr.dispatchEvent(new MouseEvent('click', { bubbles: true })); out.clicked = true; out.how = 'qr'; }
      return out;
    })()`;
    return auth.webContents.executeJavaScript(js).catch(() => null);
  }

  /** 向结算系统查询店铺信息（显示名 + 是否已登记） */
  async function lookupShop(shopId) {
    const base = (cfg.upload?.base_url || '').replace(/\/$/, '');
    const token = cfg.upload?.token || '';
    if (!base || !token) return null;
    try {
      const res = await fetch(`${base}/api/ks/shop/${encodeURIComponent(shopId)}`, {
        headers: { authorization: `Bearer ${token}` },
      });
      const text = await res.text();
      const json = JSON.parse(text);
      return json;
    } catch {
      return null;
    }
  }

  /** 读取店铺标识。实测：localStorage['seller-pc-userid'] 就是 sellerOpenId */
  async function readShopInfo(win, partition) {
    const js = `(() => {
      const out = { id: '', nick: '', source: '' };
      try {
        const u = localStorage.getItem('seller-pc-userid');
        if (u && /^\\d{10,20}$/.test(u)) { out.id = u; out.source = 'localStorage:seller-pc-userid'; }
      } catch (e) {}
      try {
        if (!out.id) {
          const m = (document.cookie || '').match(/merchantSellerId=(\\d{10,20})/);
          if (m) { out.id = m[1]; out.source = 'cookie:merchantSellerId'; }
        }
      } catch (e) {}
      try {
        const sels = ['[class*=shopName]','[class*=shop-name]','[class*=sellerName]','[class*=seller-name]','[class*=accountName]','[class*=shopTitle]'];
        for (const s of sels) {
          for (const el of document.querySelectorAll(s)) {
            const t = (el.textContent || '').trim();
            if (t && t.length >= 2 && t.length <= 24) { out.nick = t; break; }
          }
          if (out.nick) break;
        }
      } catch (e) {}
      if (!out.nick) { try { out.nick = (document.title || '').replace(/[-|_].*$/, '').trim().slice(0, 24); } catch (e) {} }
      return out;
    })()`;

    let dump = { id: '', nick: '' };
    try { dump = await win.webContents.executeJavaScript(js); } catch { /* ignore */ }

    // 诊断留痕，便于后续校准（不含订单/买家数据）
    try {
      const dir = path.join(dataRoot(), 'diag');
      fs.mkdirSync(dir, { recursive: true });
      fs.writeFileSync(path.join(dir, `bind-${Date.now()}.json`), JSON.stringify({ partition, ...dump }, null, 2), 'utf8');
    } catch { /* ignore */ }

    return { id: dump.id || null, nick: dump.nick || '' };
  }
}