// 把内部错误翻译成客户能看懂的话。客户端任何地方都不显示错误码。
export const LEVEL = { ok: 'ok', warn: 'warn', error: 'error', auth: 'auth' };

const MAP = [
  // [匹配条件, 客户看到的文案, 级别, 需要客户做什么]
  [/400002|操作太快|休息一下|captcha/i,
    '平台限流，正在自动重试', LEVEL.warn, 'none'],
  [/7210|未登录|登录失效|401|Unauthorized|NEED_LOGIN|SIGN_TIMEOUT/i,
    '登录已过期', LEVEL.auth, 'scan'],
  [/上传失败|upload/i,
    '数据已生成，正在重试上传', LEVEL.warn, 'none'],
  [/ECONN|ETIMEDOUT|ENOTFOUND|网络|socket/i,
    '网络不稳定，正在重试', LEVEL.warn, 'none'],
  [/ENOSPC|磁盘|空间不足/i,
    '电脑磁盘空间不足', LEVEL.error, 'free_space'],
  [/ENOENT|EBUSY|EPERM|文件/i,
    '本地文件读写异常', LEVEL.error, 'contact'],
];

const FALLBACK = { text: '同步暂时中断，正在自动恢复', level: LEVEL.warn, action: 'none' };

export function humanize(err) {
  const raw = typeof err === 'string' ? err : (err?.message || String(err));
  for (const [re, text, level, action] of MAP) {
    if (re.test(raw)) return { text, level, action, raw };
  }
  return { ...FALLBACK, raw };
}

/** 失败累计后的升级措辞 */
export function escalate(failCount, human) {
  if (failCount >= 6) return { text: '暂时无法同步，请联系客服', level: LEVEL.error, action: 'contact', raw: human.raw };
  if (failCount >= 2) return { text: '同步暂时中断，正在自动恢复', level: LEVEL.warn, action: 'none', raw: human.raw };
  return human;
}

export const NEEDS = {
  none: '无需操作',
  scan: '需要重新扫码',
  contact: '请联系客服',
  free_space: '请清理磁盘空间',
};