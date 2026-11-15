// 业务引擎：单店一轮
// 取数 → 解析 → 落库(幂等) → 只为有变动的日期产 xlsx → 上传 → 记录
import path from 'node:path';
import fs from 'node:fs';
import { parseOrder, FAILED_STATUS, rehash } from './parse.mjs';
import { Transport, RiskControlError, CredentialError, dateList, dayRange } from './transport.js';
import { ensureSign } from './signer.js';
import { upsertOrders, ordersByDate, recordRun, nonTerminalOldest, shopDir, updateShop, recentRuns } from './shops.js';
import { loadColumnMap, writeOutput, summarize } from './export.mjs';
import { uploadAndWait } from './uploader.js';
import { humanize, escalate } from './messages.js';
import { localLicenseOk } from './license.js';
import { extractDetail } from './detail.js';
import { writeLog } from './config.js';

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const shift = (s, n) => { const d = new Date(`${s}T12:00:00+08:00`); d.setDate(d.getDate() + n); return ymd(d); };
const today = () => ymd(new Date());

const COLUMN_MAP = path.join(path.dirname(new URL(import.meta.url).pathname).replace(/^\/([A-Za-z]:)/, '$1'), 'column_map.json');

/** 决定本轮拉哪些日期 */
export function planDates(shop, cfg, { fullRefresh = false } = {}) {
  const t = today();
  const y = shift(t, -1);
  if (fullRefresh) {
    const days = Number(cfg.sync.refresh_window_days || 0);
    // 0 = 不限窗口：从 history_start_date 全量回刷；正数则沿用最近 N 天。
    const from = days > 0 ? shift(t, -(days - 1)) : (cfg.sync.history_start_date || shift(t, -44));
    return dateList(from, t, Number(cfg.sync.max_days_per_run || 4000));
  }

  const dates = [y, t];

  const lo = nonTerminalOldest(shop.id, cfg.sync.non_terminal_window_days);
  if (lo) {
    const d = new Date(lo);
    const oldest = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const upto = shift(t, -2);
    if (oldest < upto) {
      for (const x of dateList(oldest, upto)) if (!dates.includes(x)) dates.push(x);
    }
  }
  return dates.sort();
}

/**
 * 把本地已补到的详情字段沿用过来。
 *
 * 列表接口本身不返回发货时间/真实支付时间，如果每轮都从列表重建记录，
 * 补到的值会被冲掉 → content_hash 反复变化 → 同一单被反复判为"有变动"。
 * 所以先在本地已有记录里找回这些字段，再决定要不要继续补抓。
 */
function carryOverDetail(shopId, records, dates) {
  const known = new Map();
  for (const d of dates) {
    for (const r of ordersByDate(shopId, d)) {
      if (r.send_time || r.pay_time_approx === 0) {
        known.set(String(r.oid), {
          send_time: r.send_time || '',
          pay_time: r.pay_time,
          pay_real: r.pay_time_approx === 0,
        });
      }
    }
  }
  for (const rec of records) {
    const k = known.get(String(rec.oid));
    if (!k) continue;
    if (!rec.send_time && k.send_time) rec.send_time = k.send_time;
    if (rec.pay_time_approx && k.pay_real) { rec.pay_time = k.pay_time; rec.pay_time_approx = 0; }
  }
}

/**
 * 补抓详情：拿发货时间（佣金能否算出来的开关）与真实支付时间。
 * 每轮有上限，触发风控立即停（不影响本轮已拿到的订单）。
 */
async function enrichDetails(t, records, cfg, log, result) {
  if (cfg.sync?.detail_enabled === false) return;

  const max = cfg.sync?.detail_max_per_run ?? 120;
  const todo = records.filter((r) => r.oid && !r.send_time && !FAILED_STATUS.has(r.order_status));
  const stat = { todo: todo.length, tried: 0, filled: 0 };
  let consecutiveFail = 0;

  for (const rec of todo) {
    if (stat.tried >= max) {
      log(`[detail] 本轮上限 ${max}，剩 ${todo.length - stat.tried} 单下轮继续`);
      break;
    }
    stat.tried++;

    try {
      const json = await t.fetchDetail(rec.oid);
      const d = extractDetail(json);
      if (d.send_time) { rec.send_time = d.send_time; stat.filled++; }
      if (d.pay_time && rec.pay_time_approx) { rec.pay_time = d.pay_time; rec.pay_time_approx = 0; }
      consecutiveFail = 0;
    } catch (e) {
      if (e.name === 'RiskControlError') {
        log('[detail] 触发风控，立即停止补抓');
        result.detail_risk = true;
        break;
      }
      if (e.name === 'CredentialError') throw e;
      consecutiveFail++;
      if (consecutiveFail >= 5) { log(`[detail] 连续失败 ${consecutiveFail} 次，停止`); break; }
    }
  }

  if (stat.todo) log(`[detail] 补抓 ${stat.tried}/${stat.todo}，拿到发货时间 ${stat.filled} 单`);
  result.detail = stat;
}

/**
 * 跑一轮
 * @param {object} p { shop, session, win, cfg, dates?, fullRefresh?, log? }
 */
export async function runShop({ shop, session, win, cfg, dates = null, fullRefresh = false, log = writeLog, signOverride = null }) {
  const startedAt = new Date().toISOString();
  const result = {
    shop: shop.nick, shop_id: shop.id, status: 'ok',
    inserted: 0, updated: 0, unchanged: 0, files: [], uploads: [], errors: [], dates: [],
  };

  try {
    const sign = signOverride || await ensureSign({ shopId: shop.id, session, win, log });
    const t = new Transport(cfg, session, sign, { log });

    // 优先级：显式传入 > 配置里的补抓日期 > 常规计划
    const plan = dates || (Array.isArray(cfg.sync.force_dates) && cfg.sync.force_dates.length
      ? cfg.sync.force_dates.slice()
      : planDates(shop, cfg, { fullRefresh }));
    result.dates = plan;

    const records = [];
    await t.fetchByDay({
      dates: plan,
      onDay: ({ date, total, pages, orders }) => {
        const recs = orders.map((o) => parseOrder(o, { shopId: shop.id }));
        records.push(...recs);
        log(`[${shop.nick}] ${date} 拉到 ${recs.length}/${total} 条 · ${pages} 页`);
      },
    });

    // 列表接口没有发货时间/真实支付时间 —— 用详情补（先沿用本地已知值，保持 hash 稳定）
    carryOverDetail(shop.id, records, plan);
    await enrichDetails(t, records, cfg, log, result);
    // 补抓改了字段 → 必须重算哈希，否则会被判成「无变化」而写不进库
    if (result.detail?.filled || result.detail?.tried) for (const r of records) rehash(r);

    const stat = upsertOrders(shop.id, records);
    Object.assign(result, { inserted: stat.inserted, updated: stat.updated, unchanged: stat.unchanged });
    log(`[${shop.nick}] 落库 insert=${stat.inserted} update=${stat.updated} unchanged=${stat.unchanged}`);

    // 产出：只导出有变动的日期
    const { colMap, width } = loadColumnMap(COLUMN_MAP);
    const outDir = path.join(shopDir(shop.id), 'out');
    for (const d of stat.changedDates) {
      let recs = ordersByDate(shop.id, d);
      if (cfg.export.only_distributor) recs = recs.filter((r) => r.distributor_id);
      if (!recs.length) continue;

      const fileName = `快手订单_自动_${String(shop.nick).replace(/\s+/g, '')}_${d.replace(/-/g, '')}.xlsx`;
      const out = writeOutput({
        outDir, fileName, sheetName: cfg.export.sheet_name,
        records: recs, colMap, width, overwrite: true,
        meta: { shop: shop.nick, shop_id: shop.id, date: d, generated_at: new Date().toISOString() },
      });
      result.files.push(out.xlsxPath);
      log(`[${shop.nick}] 产出 ${path.basename(out.xlsxPath)} (${out.rows} 行)`);

      // 授权到期/停用：只停自动上传，订单照抓、本地照存。
      // 这样收费有约束（不再往结算系统送数据），又不会让客户丢数据，续费后补传即可。
      const lic = localLicenseOk(cfg);
      if (!lic.ok) {
        result.upload_skipped = lic.reason;
        log(`[${shop.nick}] 授权不可用（${lic.reason}），已生成文件但跳过上传`);
        continue;
      }

      const up = await uploadAndWait(cfg, shop, out.xlsxPath, { log });
      result.uploads.push({ file: path.basename(out.xlsxPath), ...up });
      if (up.skipped) continue;
      if (!up.finished || (up.fail || 0) > 0) result.status = 'warn';
    }

    // 统计今日单量，用于店铺列表展示
    const todayCount = ordersByDate(shop.id, today()).length;
    updateShop(shop.id, {
      last_sync_at: new Date().toISOString(),
      last_status: result.status === 'ok' ? 'ok' : 'warn',
      last_error: '',
      stats: { today: todayCount, last_run_rows: records.length },
    });

    recordRun(shop.id, {
      started_at: startedAt, finished_at: new Date().toISOString(),
      status: result.status, inserted: stat.inserted, updated: stat.updated,
      unchanged: stat.unchanged, rows: records.length, files: result.files.length,
    });
  } catch (e) {
    const h = humanize(e);
    result.status = h.level === 'auth' ? 'need_login' : 'error';
    result.errors.push(h.raw);
    result.human = h;

    const fails = recentRuns(shop.id, 5).filter((r) => r.status !== 'ok').length + 1;
    const esc = escalate(fails, h);

    updateShop(shop.id, {
      last_sync_at: new Date().toISOString(),
      last_status: result.status,
      last_error: h.raw,
    });
    recordRun(shop.id, {
      started_at: startedAt, finished_at: new Date().toISOString(),
      status: result.status, message: h.raw, fails,
    });
    log(`[${shop.nick}] 失败(${result.status}): ${esc.text} | raw=${h.raw}`);
  }

  return result;
}