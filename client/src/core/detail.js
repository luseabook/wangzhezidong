// 订单详情解析
//
// 详情接口返回的不是扁平数据，而是一棵 UI 组件树：
//   { fields: { infoBarId: 'shipmentTime', title: '发货时间', data: [{ text: '2026-08-28 12:24:52' }] } }
//
// 所以按 infoBarId 遍历收集，比按层级路径取更抗结构变化。
const RE_TPL = /\$\{[^}]*\}/g;   // 去掉 "${0}" 这类模板残留

export const DETAIL_IDS = [
  'createTimeDesc',
  'payTimeDesc',
  'shipmentTime',
  'orderSuccessTimeDesc',
  'orderId',
  'buyerNick',
  'payChannelDesc',
];

/**
 * @param {object} payload 详情接口 JSON（result=1 的整个响应）
 * @returns {{create_time?:string, pay_time?:string, send_time?:string, success_time?:string,
 *            order_id?:string, buyer_nick?:string, pay_channel?:string}}
 */
export function extractDetail(payload) {
  const out = {};

  const walk = (o) => {
    if (!o || typeof o !== 'object') return;
    const f = o.fields;
    if (f && typeof f === 'object' && f.infoBarId && Array.isArray(f.data) && f.data.length) {
      const raw = f.data[0] && f.data[0].text;
      const text = typeof raw === 'string' ? raw.replace(RE_TPL, '').trim() : '';
      if (text) {
        switch (f.infoBarId) {
          case 'createTimeDesc': out.create_time = text; break;
          case 'payTimeDesc': out.pay_time = text; break;
          case 'shipmentTime': out.send_time = text; break;
          case 'orderSuccessTimeDesc': out.success_time = text; break;
          case 'orderId': out.order_id = text; break;
          case 'buyerNick': out.buyer_nick = text; break;
          case 'payChannelDesc': out.pay_channel = text; break;
          default: break;
        }
      }
    }
    for (const v of Object.values(o)) walk(v);
  };

  walk(payload);
  return out;
}

/** 详情是否拿到了我们需要的东西 */
export function detailUsable(d) {
  return !!(d && (d.send_time || d.pay_time));
}