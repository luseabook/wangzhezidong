// 配置：<root>\config.json
// 客户端不需要用户编辑任何配置；根目录由主进程注入。
import fs from 'node:fs';
import path from 'node:path';

let ROOT = null;

/** 主进程启动时调用：setRoot(app.getPath('appData')) */
export function setRoot(dir) {
  ROOT = path.join(dir, 'KsSync');
  fs.mkdirSync(ROOT, { recursive: true });
  return ROOT;
}

export function dataRoot() {
  if (!ROOT) {
    ROOT = path.join(process.env.APPDATA || process.env.HOME || '.', 'KsSync');
    fs.mkdirSync(ROOT, { recursive: true });
  }
  return ROOT;
}

const DEFAULT = {
  license: '',
  license_server: '',       // 授权中心地址，安装包预置
  machine_id: '',           // 首次运行时生成
  autostart: true,          // 开机自启（安装后默认开启）
  update: {
    feed_url: '',            // 由授权中心激活时下发，或手动配置
    channel: 'latest',
    check_interval_hours: 4,
    auto_download: true,
    allow_prerelease: false,
  },
  upload: { base_url: '', token: '', poll_timeout_s: 180 },
  sync: {
    interval_minutes: 60,
    request_delay_ms: 1300,
    page_size: 10,
    detail_enabled: true,       // 用详情接口补发货时间（佣金能否算出来的开关）
    detail_max_per_run: 120,    // 每轮最多补抓多少单，避免拖长同步与触发风控              // 硬锁：平台 pageSize>10 会触发风控
    non_terminal_window_days: 15,
    refresh_window_days: 45,
  },
  export: { sheet_name: 'Sheet1', only_distributor: true },
};

export function configPath() { return path.join(dataRoot(), 'config.json'); }

export function loadConfig() {
  try {
    const raw = JSON.parse(fs.readFileSync(configPath(), 'utf8'));
    return merge(structuredClone(DEFAULT), raw);
  } catch {
    return structuredClone(DEFAULT);
  }
}

export function saveConfig(cfg) {
  fs.mkdirSync(dataRoot(), { recursive: true });
  fs.writeFileSync(configPath(), JSON.stringify(cfg, null, 2), 'utf8');
  return cfg;
}

function merge(base, over) {
  for (const [k, v] of Object.entries(over || {})) {
    if (v && typeof v === 'object' && !Array.isArray(v)) base[k] = merge(base[k] || {}, v);
    else base[k] = v;
  }
  return base;
}

export function writeLog(...args) {
  const line = `[${new Date().toISOString()}] ${args
    .map((a) => (typeof a === 'string' ? a : JSON.stringify(a)))
    .join(' ')}\n`;
  try {
    const dir = path.join(dataRoot(), 'logs');
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, 'app.log'), line, 'utf8');
  } catch { /* ignore */ }
  process.stdout.write(line);
}