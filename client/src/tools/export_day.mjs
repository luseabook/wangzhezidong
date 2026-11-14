// 把本地某一天的订单重新导出成 xlsx（不上传）
// 运行: node src\tools\export_day.mjs [shopId] [date]
import path from 'node:path';
import { loadConfig } from '../core/config.js';
import { loadColumnMap, writeOutput, summarize } from '../core/export.mjs';
import { ordersByDate, shopDir } from '../core/shops.js';

const HERE = path.dirname(new URL(import.meta.url).pathname).replace(/^\/([A-Za-z]:)/, '$1');
const COLUMN_MAP = path.join(HERE, '..', 'core', 'column_map.json');

const SHOP_ID = process.argv[2] || '140000122179234';
const DATE = process.argv[3] || '2026-08-28';

const cfg = loadConfig();
let recs = ordersByDate(SHOP_ID, DATE);
if (cfg.export?.only_distributor) recs = recs.filter((r) => r.distributor_id);

const { colMap, width } = loadColumnMap(COLUMN_MAP);
const outDir = path.join(shopDir(SHOP_ID), 'out');

const nick = String(cfg.shops?.[SHOP_ID]?.nick || '2号店').replace(/\s+/g, '');
const out = writeOutput({
  outDir,
  fileName: `快手订单_自动_${nick}_${DATE.replace(/-/g, '')}.xlsx`,
  sheetName: cfg.export?.sheet_name || '订单',
  records: recs,
  colMap,
  width,
  overwrite: true,
  meta: { shop_id: SHOP_ID, date: DATE, generated_at: new Date().toISOString() },
});

console.log(`导出 ${out.rows} 行 -> ${out.xlsxPath}`);
console.log('统计:', JSON.stringify(summarize(recs)));