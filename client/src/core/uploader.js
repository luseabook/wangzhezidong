// 上传器：把 xlsx 交给结算系统，并轮询导入结果
import fs from 'node:fs';
import path from 'node:path';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export class UploadError extends Error {
  constructor(msg, o = {}) { super(msg); this.name = 'UploadError'; Object.assign(this, o); }
}

export async function uploadAndWait(cfg, shop, xlsxPath, { log = () => {} } = {}) {
  const base = (cfg.upload?.base_url || '').replace(/\/$/, '');
  const token = cfg.upload?.token || '';
  if (!base) return { skipped: true, reason: '未配置上传地址' };
  if (!token) return { skipped: true, reason: '未配置上传令牌' };

  const buf = fs.readFileSync(xlsxPath);
  const fd = new FormData();
  fd.append('shop_id', String(shop.id));
  fd.append('xlsx', new Blob([buf], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  }), path.basename(xlsxPath));

  log(`[upload] ${path.basename(xlsxPath)} (${(buf.length / 1024).toFixed(0)}KB) → ${base}`);
  let res;
  try {
    res = await fetch(`${base}/api/ks/import`, {
      method: 'POST',
      headers: { authorization: `Bearer ${token}` },
      body: fd,
    });
  } catch (e) {
    throw new UploadError(`上传网络错误: ${e.message}`);
  }

  const text = await res.text();
  let json = null; try { json = JSON.parse(text); } catch { /* ignore */ }
  if (!res.ok || !json?.ok) {
    throw new UploadError(`上传失败 HTTP ${res.status}: ${text.slice(0, 200)}`);
  }
  log(`[upload] 已受理 log_id=${json.log_id}`);
  return poll(cfg, json.log_id, { log });
}

export async function poll(cfg, logId, { log = () => {} } = {}) {
  const base = (cfg.upload.base_url || '').replace(/\/$/, '');
  const token = cfg.upload.token || '';
  const deadline = Date.now() + (cfg.upload.poll_timeout_s || 180) * 1000;
  let last = null;

  while (Date.now() < deadline) {
    let j = null;
    try {
      const res = await fetch(`${base}/api/ks/import/${logId}`, {
        headers: { authorization: `Bearer ${token}` },
      });
      if (res.ok) j = await res.json();
    } catch { /* 重试 */ }

    if (j) {
      last = j;
      log(`[poll] status=${j.status} total=${j.total} success=${j.success} fail=${j.fail}`);
      if (j.finished) return { log_id: logId, ...j, finished: true };
    }
    await sleep(10000);
  }
  return { log_id: logId, ...(last || {}), finished: false };
}