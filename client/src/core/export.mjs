// 产出层：记录 → 按 column_map 落位的 xlsx + 对账 json
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { buildXlsx } from './xlsx.mjs';

const money = (cents) => `¥${((cents || 0) / 100).toFixed(2)}`;

/** "2026-08-26 23:27" → "2026-08-26 23:27:00"（G5：秒级精度不可得，补零） */
function withSeconds(t) {
  if (!t) return '';
  const s = String(t).trim();
  return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(s) ? `${s}:00` : s;
}

export function loadColumnMap(mapPath) {
  const raw = JSON.parse(readFileSync(mapPath, 'utf8'));
  const colMap = raw.columns || raw;
  const width = raw._width || Math.max(...Object.keys(colMap).map(Number)) + 1;
  return { colMap, width, note: raw._note || '' };
}

function valueFor(field, r) {
  switch (field) {
    case 'oid': return String(r.oid ?? '');
    case 'create_time': return withSeconds(r.create_time);
    case 'pay_time': return withSeconds(r.pay_time || r.create_time);
    case 'order_status': return r.order_status || '';
    case 'actual_payment': return money(r.actual_payment_cents);
    case 'express_fee': return money(r.express_cents);
    case 'shop_discount': return money(r.shop_discount_cents);
    case 'platform_discount': return money(r.plat_discount_cents);
    case 'anchor_discount': return money(r.anchor_discount_cents);
    case 'qty': return String(r.qty ?? '');
    case 'refund_status': return r.refund_status || '-';
    case 'item_title': return r.item_title || '';
    case 'item_id': return String(r.item_id ?? '');
    case 'price': return money(r.price_cents);
    case 'distributor_id': return r.distributor_id ? String(r.distributor_id) : '';
    case 'distributor_name': return r.distributor_name || '';
    case 'commission': return money(r.commission_cents);
    case 'commission_rate': return r.commission_rate || '';
    case 'send_time': return withSeconds(r.send_time || '');
    default: return '';
  }
}

/** 记录数组 → 二维行数组（首行表头） */
export function buildRows(records, { colMap, width }) {
  const header = new Array(width).fill('');
  for (const [idx, def] of Object.entries(colMap)) header[Number(idx)] = def.header || '';

  const rows = [header];
  for (const r of records) {
    const row = new Array(width).fill('');
    for (const [idx, def] of Object.entries(colMap)) row[Number(idx)] = valueFor(def.field, r);
    rows.push(row);
  }
  return rows;
}

export function summarize(records, extra = {}) {
  const dist = records.filter((r) => r.distributor_id);
  const byStatus = {};
  for (const r of records) byStatus[r.order_status || '(空)'] = (byStatus[r.order_status || '(空)'] || 0) + 1;
  const people = {};
  for (const r of dist) {
    const k = `${r.distributor_name || '?'}(${r.distributor_id})`;
    people[k] = (people[k] || 0) + 1;
  }
  return {
    rows: records.length,
    distributor_rows: dist.length,
    persons: new Set(dist.map((r) => r.distributor_id)).size,
    products: new Set(dist.map((r) => r.item_title).filter(Boolean)).size,
    amount_price_x_qty_cents: dist.reduce((a, r) => a + (r.price_cents || 0) * (r.qty || 0), 0),
    amount_actual_payment_cents: dist.reduce((a, r) => a + (r.actual_payment_cents || 0), 0),
    express_cents: dist.reduce((a, r) => a + (r.express_cents || 0), 0),
    platform_discount_cents: dist.reduce((a, r) => a + (r.plat_discount_cents || 0), 0),
    by_status: byStatus,
    by_person: people,
    pay_time_approx_rows: records.filter((r) => r.pay_time_approx).length,
    multi_item_suspect: records.filter((r) => r.mulit_item_suspect).length,
    missing_distributor_id: records.filter((r) => !r.distributor_id).length,
    ...extra,
  };
}

/**
 * 写出 xlsx + 同名 json
 * @returns {{xlsxPath:string, jsonPath:string, rows:number}}
 */
export function writeOutput({ outDir, fileName, sheetName = 'Sheet1', records, colMap, width, meta = {}, overwrite = false }) {
  mkdirSync(outDir, { recursive: true });
  const rows = buildRows(records, { colMap, width });
  const buf = buildXlsx({ sheetName, rows });
  let target = join(outDir, fileName);
  if (overwrite) {
    // 客户端：同一天重新生成时覆盖同名文件，避免产生 _2/_3 垃圾
    writeFileSync(target, buf);
  } else {
    let n = 1;
    while (true) {
      try {
        writeFileSync(target, buf, { flag: 'wx' });   // 不覆盖
        break;
      } catch (e) {
        if (e.code !== 'EEXIST') throw e;
        n += 1;
        target = join(outDir, fileName.replace(/\.xlsx$/, `_${n}.xlsx`));
      }
    }
  }
  const jsonPath = target.replace(/\.xlsx$/, '.json');
  const summary = summarize(records, meta);
  writeFileSync(jsonPath, JSON.stringify(summary, null, 2), 'utf8');
  return { xlsxPath: target, jsonPath, rows: rows.length - 1 };
}