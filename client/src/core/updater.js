// 在线更新：electron-updater + 通用 HTTPS 更新源
// 更新源由授权中心激活时下发，或写入 config.update.feed_url。
import fs from 'node:fs';
import { app, BrowserWindow } from 'electron';
import electronUpdater from 'electron-updater';
import { loadConfig } from './config.js';

const PKG_URL = new URL('../../package.json', import.meta.url);

/** 打包后用 app.getVersion；开发模式 Electron 会返回自身版本，回退到 package.json。 */
export function appVersion() {
  if (app.isPackaged) return app.getVersion();
  try {
    const pkg = JSON.parse(fs.readFileSync(PKG_URL, 'utf8'));
    if (pkg?.version) return String(pkg.version);
  } catch { /* ignore */ }
  return app.getVersion();
}

const { autoUpdater } = electronUpdater;
const HOUR = 3600 * 1000;
let getConfig = () => loadConfig();
let log = () => {};
let initialized = false;
let lastFeed = '';
let timer = null;

const state = {
  status: 'idle',
  currentVersion: appVersion(),
  availableVersion: '',
  progress: 0,
  bytesPerSecond: 0,
  error: '',
  releaseNotes: '',
  feedConfigured: false,
  channel: 'latest',
};

function feedOf(cfg) {
  return String(cfg?.update?.feed_url || process.env.KS_UPDATE_URL || '').trim().replace(/\/+$/, '');
}
function channelOf(cfg) {
  return String(cfg?.update?.channel || 'latest').trim() || 'latest';
}

function broadcast() {
  for (const win of BrowserWindow.getAllWindows()) {
    try {
      if (!win.isDestroyed()) win.webContents.send('update:status', { ...state });
    } catch { /* ignore */ }
  }
}
function setState(patch) {
  Object.assign(state, patch);
  broadcast();
}
function applyFeed(cfg) {
  const feed = feedOf(cfg);
  const channel = channelOf(cfg);
  state.feedConfigured = !!feed;
  state.channel = channel;
  if (!feed) return false;

  autoUpdater.autoDownload = cfg?.update?.auto_download !== false;
  autoUpdater.autoInstallOnAppQuit = true;
  autoUpdater.allowPrerelease = !!cfg?.update?.allow_prerelease;
  autoUpdater.channel = channel;

  if (feed !== lastFeed) {
    autoUpdater.setFeedURL({ provider: 'generic', url: feed });
    lastFeed = feed;
  }
  return true;
}

export function initUpdater({ getConfig: getCfg, log: logger } = {}) {
  if (initialized) return;
  initialized = true;
  if (getCfg) getConfig = getCfg;
  if (logger) log = logger;

  autoUpdater.logger = {
    info: (...a) => log('[update]', ...a),
    warn: (...a) => log('[update]', ...a),
    error: (...a) => log('[update]', ...a),
    debug: () => {},
  };

  autoUpdater.on('checking-for-update', () => setState({ status: 'checking', error: '' }));
  autoUpdater.on('update-available', (info) => setState({
    status: 'available',
    availableVersion: String(info?.version || ''),
    releaseNotes: typeof info?.releaseNotes === 'string' ? info.releaseNotes : '',
    error: '',
  }));
  autoUpdater.on('update-not-available', () => setState({
    status: 'up_to_date',
    availableVersion: '',
    progress: 0,
    error: '',
  }));
  autoUpdater.on('download-progress', (p) => setState({
    status: 'downloading',
    progress: Math.round(Number(p?.percent || 0)),
    bytesPerSecond: Math.round(Number(p?.bytesPerSecond || 0)),
  }));
  autoUpdater.on('update-downloaded', (info) => setState({
    status: 'downloaded',
    availableVersion: String(info?.version || state.availableVersion || ''),
    progress: 100,
    error: '',
  }));
  autoUpdater.on('error', (e) => setState({
    status: 'error',
    error: String(e?.message || e || '更新失败'),
  }));

  const cfg = getConfig();
  state.currentVersion = appVersion();
  state.channel = channelOf(cfg);
  state.feedConfigured = !!feedOf(cfg);
  state.status = state.feedConfigured ? 'idle' : 'not_configured';

  setTimeout(() => checkForUpdates(), 15 * 1000);
  const hours = Math.max(1, Number(cfg?.update?.check_interval_hours || 4));
  timer = setInterval(() => checkForUpdates(), hours * HOUR);
  if (timer.unref) timer.unref();
}

export function updaterStatus() {
  const cfg = getConfig();
  state.currentVersion = appVersion();
  state.feedConfigured = !!feedOf(cfg);
  state.channel = channelOf(cfg);
  return { ...state };
}

export async function checkForUpdates() {
  const cfg = getConfig();
  state.currentVersion = appVersion();
  if (!applyFeed(cfg)) {
    setState({ status: 'not_configured', error: '', availableVersion: '', progress: 0 });
    return { ...state };
  }
  if (!app.isPackaged) {
    setState({ status: 'dev_mode', error: '开发模式不执行在线更新，请用安装包验证' });
    return { ...state };
  }
  try {
    setState({ status: 'checking', error: '' });
    await autoUpdater.checkForUpdates();
  } catch (e) {
    setState({ status: 'error', error: String(e?.message || e || '检查更新失败') });
  }
  return { ...state };
}

export async function downloadUpdate() {
  if (!applyFeed(getConfig()) || !app.isPackaged) return { ...state };
  try {
    setState({ status: 'downloading', progress: 0, error: '' });
    await autoUpdater.downloadUpdate();
  } catch (e) {
    setState({ status: 'error', error: String(e?.message || e || '下载更新失败') });
  }
  return { ...state };
}

export function installUpdate() {
  if (!app.isPackaged) return { ok: false, reason: 'DEV_MODE' };
  autoUpdater.quitAndInstall(false, true);
  return { ok: true };
}
