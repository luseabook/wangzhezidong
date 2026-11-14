// 归一化：把列表接口的展示型结构拆成扁平字段
// 关键点：payInfoList 是「标签-金额」对，必须按标签名匹配，不能按下标猜

import { createHash } from 'node:crypto';

/** 金额字符串 → 分（四舍五入，避免 39.9*100=3989.99 的浮点误差） */
export function toCents(v) {
  if (v === null || v === undefined || v === '') return null;
  const s = String(v).replace(/[¥￥,\s元]/g, '');
  const m = s.match(/-?\d+(?:\.\d+)?/);
  if (!m) return null;
  return Math.round(Number(m[0]) * 100);
}

/** "x3" → 3 */
export function toQty(v) {
  if (v === null || v === undefined) return null;
  const m = String(v).match(/\d+/);
  return m ? Number(m[0]) : null;
}

/** 在 [{key:'达人ID：', value:{content:'...'}}] 里按 key 名取值 */
export function kv(list, re) {
  if (!Array.isArray(list)) return undefined;
  for (const it of list) {
    if (it && typeof it.key === 'string' && re.test(it.key)) return it.value?.content;
  }
  return undefined;
}

/**
 * 拆 paymentData.payInfoList
 * 实测形态：
 *   [在线支付, ￥39.90]
 *   [在线支付, ￥104.90, 含商品运费, ￥5.00]
 *   [在线支付, ￥198.00, 含支付优惠, ￥0.01]
 *   [在线支付, ￥396.00, 含平台补贴, ￥1.00]
 */
export function parsePayInfo(payInfoList) {
  const out = {
    payMethod: null, actualPaymentCents: null,
    expressCents: 0, platDiscountCents: 0, shopDiscountCents: 0,
    anchorDiscountCents: 0, payDiscountCents: 0,
    raw: null, unknownLabels: [],
  };
  if (!Array.isArray(payInfoList) || !payInfoList.length) return out;

  const tokens = payInfoList.map((x) => String(x?.text ?? '').trim()).filter(Boolean);
  out.raw = tokens.join(' | ');

  // 第一段：支付方式 + 实付款
  if (tokens.length) out.payMethod = tokens[0];
  const firstAmtIdx = tokens.findIndex((t, i) => i > 0 && /^[¥￥]/.test(t));
  if (firstAmtIdx > 0) out.actualPaymentCents = toCents(tokens[firstAmtIdx]);

  // 后续：标签 + 金额成对（跳过已认领的实付款那一对）
  for (let i = 1; i < tokens.length; i++) {
    if (i === firstAmtIdx) continue;
    const t = tokens[i];
    if (!/^[¥￥]/.test(t)) continue;
    const label = tokens[i - 1] || '';
    const cents = toCents(t);
    if (cents === null) continue;
    if (/商品运费|运费/.test(label)) out.expressCents += cents;
    else if (/平台补贴/.test(label)) out.platDiscountCents += cents;
    else if (/店铺优惠|商家优惠/.test(label)) out.shopDiscountCents += cents;
    else if (/主播补贴|达人补贴/.test(label)) out.anchorDiscountCents += cents;
    else if (/支付优惠/.test(label)) out.payDiscountCents += cents;
    else if (!/^[¥￥]/.test(label)) out.unknownLabels.push(`${label}=${t}`);
  }
  return out;
}

/** 地址串拆省市区街道（尽力而为，失败则整串落 address） */
export function parseAddress(addressText) {
  const out = { province: '', city: '', district: '', town: '', detail: '' };
  if (!addressText) return out;
  let rest = String(addressText).trim().replace(/[，,]/g, '');

  const take = (re) => {
    const m = rest.match(re);
    if (!m) return '';
    rest = rest.slice(m[0].length);
    return m[0];
  };

  out.province = take(/^(.+?(?:省|自治区|特别行政区))/);
  if (!out.province) out.province = take(/^(.+?市)/);          // 直辖市
  out.city = take(/^(.+?(?:市|自治州|地区|盟))/);
  out.district = take(/^(.+?(?:区|县|旗|市))/);
  out.town = take(/^(.+?(?:镇|乡|街道|民族乡))/);
  out.detail = rest;
  return out;
}

/** 状态文本 → 是否终态 */
export const TERMINAL_STATUS = new Set(['交易成功', '订单关闭', '交易关闭']);
export const FAILED_STATUS = new Set(['订单关闭', '交易关闭']);

export function isTerminal(statusText) {
  return TERMINAL_STATUS.has(String(statusText || '').trim());
}

/** 从 jumpUrl 里抠商品 ID */
export function itemIdFromUrl(url) {
  if (!url) return null;
  const m = String(url).match(/[?&]id=(\d+)/);
  return m ? m[1] : null;
}

/** 主解析入口：一条 orderList 数组项 → 扁平记录 */
export function parseOrder(order, { shopId } = {}) {
  const header = order.headerData?.orderInfoList || [];
  const goods = order.goods || {};
  const status = order.statusData || {};
  const consignee = order.buyerAndConsignee?.consigneeInfo || {};
  const roles = order.roleUser?.roleInfoList || [];
  const dist = roles.find((r) => r.roleType === 'distributor') || null;
  const team = roles.find((r) => r.roleType === 'activityUser') || null;

  const pay = parsePayInfo(order.paymentData?.payInfoList);
  const addr = parseAddress(consignee.address);

  const statusText = status.orderStatusList?.[0]?.desc || '';
  const refundList = status.refundStatusList || [];
  const refundText = refundList.length
    ? refundList.map((r) => r.desc || r.text || '').filter(Boolean).join('/') || '-'
    : '-';

  const createText = kv(header, /创建时间|下单时间/) || '';
  const createTs = createText ? new Date(`${createText.replace(' ', 'T')}:00+08:00`).getTime() : null;

  const itemUrl = goods.itemTitle?.jumpUrl || '';

  const rec = {
    shop_id: shopId || null,
    oid: String(order.oid || kv(header, /订单编号/) || '').trim(),
    create_time: createText,
    create_ts: Number.isFinite(createTs) ? createTs : null,
    pay_time: createText,                      // G2：近似，见 payTimeApprox
    pay_time_approx: true,
    send_time: '',                             // 发货时间：列表接口没有，由详情补抓写入
    order_status: statusText,
    refund_status: refundText,
    is_terminal: isTerminal(statusText) ? 1 : 0,

    distributor_id: dist ? (kv(dist.content, /达人ID/) ?? null) : null,
    distributor_name: dist ? (kv(dist.content, /达人昵称/) ?? null) : null,
    team_id: team ? (kv(team.content, /团长ID/) ?? null) : null,
    team_name: team ? (kv(team.content, /团长昵称/) ?? null) : null,

    item_id: itemIdFromUrl(itemUrl),
    item_title: goods.itemTitle?.itemTitleDesc || goods.itemTitle?.text || '',
    sku_name: goods.skuInfo?.[0]?.text || '',
    price_cents: toCents(goods.price?.desc),
    qty: toQty(goods.count?.desc),

    commission_cents: dist ? toCents(kv(dist.content, /预计佣金|预估推广佣金/)) : null,
    commission_rate: dist ? (kv(dist.content, /分佣比例|佣金比例/) ?? null) : null,

    consignee_mask: consignee.consigneeName || '',
    phone_mask: consignee.consigneePhone || '',
    province: addr.province, city: addr.city, district: addr.district, town: addr.town,
    address_text: consignee.address || '',

    pay_method: pay.payMethod,
    actual_payment_cents: pay.actualPaymentCents,
    express_cents: pay.expressCents,
    plat_discount_cents: pay.platDiscountCents,
    shop_discount_cents: pay.shopDiscountCents,
    anchor_discount_cents: pay.anchorDiscountCents,
    pay_discount_cents: pay.payDiscountCents,
    pay_text: pay.raw,
    unknown_pay_labels: pay.unknownLabels,

    mulit_item_suspect: (goods.skuInfo?.length || 0) > 1 ? 1 : 0,
    raw_json: JSON.stringify(order),
  };

  // content_hash 必须基于「业务字段」，不能用整包 raw_json。
  // 实测 raw_json 里含 statusData.countDown.serverTime / requestId 等每次请求都变的字段，
  // 用整包会导致每次运行都判定为"内容变化"，幂等彻底失效、并且每轮重复产文件。
  const HASH_FIELDS = [
    'oid', 'create_time', 'order_status', 'refund_status',
    // 发货时间与真实支付时间来自详情接口，会改变结算结果，必须参与哈希，
    // 否则补抓到的值会被判成「无变化」而写不进本地库。
    'pay_time', 'send_time',
    'distributor_id', 'distributor_name', 'team_id', 'team_name',
    'item_id', 'item_title', 'sku_name', 'price_cents', 'qty',
    'commission_cents', 'commission_rate',
    'consignee_mask', 'phone_mask',
    'province', 'city', 'district', 'town', 'address_text',
    'actual_payment_cents', 'express_cents', 'plat_discount_cents',
    'shop_discount_cents', 'anchor_discount_cents', 'pay_discount_cents',
  ];
  rehash(rec);
  return rec;
}

// 参与内容哈希的业务字段（模块级，供 parseOrder 与补抓后的 rehash 共用）
const HASH_FIELDS = [
  'oid', 'create_time', 'order_status', 'refund_status',
  // 发货时间与真实支付时间来自详情接口，会改变结算结果，必须参与哈希，
  // 否则补抓到的值会被判成「无变化」而写不进本地库。
  'pay_time', 'send_time',
  'distributor_id', 'distributor_name', 'team_id', 'team_name',
  'item_id', 'item_title', 'sku_name', 'price_cents', 'qty',
  'commission_cents', 'commission_rate',
  'consignee_mask', 'phone_mask',
  'province', 'city', 'district', 'town', 'address_text',
  'actual_payment_cents', 'express_cents', 'plat_discount_cents',
  'shop_discount_cents', 'anchor_discount_cents', 'pay_discount_cents',
];

/** 重算内容哈希（补抓详情后字段变了，必须重算，否则会被判成「无变化」而写不进库） */
export function rehash(rec) {
  rec.content_hash = createHash('sha256')
    .update(JSON.stringify(HASH_FIELDS.map((f) => rec[f] ?? null)))
    .digest('hex')
    .slice(0, 32);
  return rec.content_hash;
}