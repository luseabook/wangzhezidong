<?php

/**
 * A helper file for Dcat Admin, to provide autocomplete information to your IDE
 *
 * This file should not be included in your code, only analyzed by your IDE!
 *
 * @author jqh <841324345@qq.com>
 */
namespace Dcat\Admin {
    use Illuminate\Support\Collection;

    /**
     * @property Grid\Column|Collection created_at
     * @property Grid\Column|Collection detail
     * @property Grid\Column|Collection id
     * @property Grid\Column|Collection name
     * @property Grid\Column|Collection type
     * @property Grid\Column|Collection updated_at
     * @property Grid\Column|Collection version
     * @property Grid\Column|Collection is_enabled
     * @property Grid\Column|Collection extension
     * @property Grid\Column|Collection icon
     * @property Grid\Column|Collection order
     * @property Grid\Column|Collection parent_id
     * @property Grid\Column|Collection uri
     * @property Grid\Column|Collection menu_id
     * @property Grid\Column|Collection permission_id
     * @property Grid\Column|Collection http_method
     * @property Grid\Column|Collection http_path
     * @property Grid\Column|Collection slug
     * @property Grid\Column|Collection role_id
     * @property Grid\Column|Collection user_id
     * @property Grid\Column|Collection value
     * @property Grid\Column|Collection avatar
     * @property Grid\Column|Collection password
     * @property Grid\Column|Collection remember_token
     * @property Grid\Column|Collection username
     * @property Grid\Column|Collection amount
     * @property Grid\Column|Collection apply_at
     * @property Grid\Column|Collection master_id
     * @property Grid\Column|Collection pay_at
     * @property Grid\Column|Collection remark
     * @property Grid\Column|Collection status
     * @property Grid\Column|Collection uuid
     * @property Grid\Column|Collection cashed
     * @property Grid\Column|Collection distributorId
     * @property Grid\Column|Collection today_can_cash
     * @property Grid\Column|Collection total_commission
     * @property Grid\Column|Collection total_order
     * @property Grid\Column|Collection total_pay
     * @property Grid\Column|Collection vaild_order
     * @property Grid\Column|Collection cash
     * @property Grid\Column|Collection commission1
     * @property Grid\Column|Collection commission2
     * @property Grid\Column|Collection date
     * @property Grid\Column|Collection refund_commission
     * @property Grid\Column|Collection refund_order
     * @property Grid\Column|Collection total_cash
     * @property Grid\Column|Collection total_sale
     * @property Grid\Column|Collection total_uncommission
     * @property Grid\Column|Collection distributorName
     * @property Grid\Column|Collection connection
     * @property Grid\Column|Collection exception
     * @property Grid\Column|Collection failed_at
     * @property Grid\Column|Collection payload
     * @property Grid\Column|Collection queue
     * @property Grid\Column|Collection attempts
     * @property Grid\Column|Collection available_at
     * @property Grid\Column|Collection reserved_at
     * @property Grid\Column|Collection account
     * @property Grid\Column|Collection canCommission
     * @property Grid\Column|Collection cash_money
     * @property Grid\Column|Collection commission_money
     * @property Grid\Column|Collection commissioned
     * @property Grid\Column|Collection parent_rate
     * @property Grid\Column|Collection rate1
     * @property Grid\Column|Collection rate2
     * @property Grid\Column|Collection realname
     * @property Grid\Column|Collection wechat_rate1
     * @property Grid\Column|Collection wechat_rate2
     * @property Grid\Column|Collection after_money
     * @property Grid\Column|Collection before_money
     * @property Grid\Column|Collection content
     * @property Grid\Column|Collection is_parent
     * @property Grid\Column|Collection money
     * @property Grid\Column|Collection oid
     * @property Grid\Column|Collection rate
     * @property Grid\Column|Collection messages
     * @property Grid\Column|Collection order_log_id
     * @property Grid\Column|Collection fail
     * @property Grid\Column|Collection failFile
     * @property Grid\Column|Collection file
     * @property Grid\Column|Collection sellerNick
     * @property Grid\Column|Collection sellerOpenId
     * @property Grid\Column|Collection success
     * @property Grid\Column|Collection total
     * @property Grid\Column|Collection unPayOrCps
     * @property Grid\Column|Collection unSend
     * @property Grid\Column|Collection allCommission
     * @property Grid\Column|Collection doneCommission
     * @property Grid\Column|Collection unCommission
     * @property Grid\Column|Collection commission1_rate
     * @property Grid\Column|Collection commission1Time
     * @property Grid\Column|Collection commission2_rate
     * @property Grid\Column|Collection commission2Time
     * @property Grid\Column|Collection commission_rate
     * @property Grid\Column|Collection commissionRate
     * @property Grid\Column|Collection commissionTotal
     * @property Grid\Column|Collection cpsDetail
     * @property Grid\Column|Collection cpsPayStatus
     * @property Grid\Column|Collection cpsStatus
     * @property Grid\Column|Collection cpsType
     * @property Grid\Column|Collection createTime
     * @property Grid\Column|Collection discountFee
     * @property Grid\Column|Collection estimatedIncome
     * @property Grid\Column|Collection expressFee
     * @property Grid\Column|Collection goodDiscountFee
     * @property Grid\Column|Collection importStatus
     * @property Grid\Column|Collection itemId
     * @property Grid\Column|Collection log_id
     * @property Grid\Column|Collection num
     * @property Grid\Column|Collection orderChannel
     * @property Grid\Column|Collection orderDetail
     * @property Grid\Column|Collection originalPrice
     * @property Grid\Column|Collection parent_commission
     * @property Grid\Column|Collection parent_commission1
     * @property Grid\Column|Collection parent_commission2
     * @property Grid\Column|Collection payTime
     * @property Grid\Column|Collection platformDpRate
     * @property Grid\Column|Collection price
     * @property Grid\Column|Collection promoterExcitationInCome
     * @property Grid\Column|Collection promoterServiceInCome
     * @property Grid\Column|Collection refundDetail
     * @property Grid\Column|Collection refundStatus
     * @property Grid\Column|Collection refundTime
     * @property Grid\Column|Collection relItemId
     * @property Grid\Column|Collection relSkuId
     * @property Grid\Column|Collection sellerType
     * @property Grid\Column|Collection sendTime
     * @property Grid\Column|Collection settlementBizType
     * @property Grid\Column|Collection skuId
     * @property Grid\Column|Collection skuNick
     * @property Grid\Column|Collection totalFee
     * @property Grid\Column|Collection updateTime
     * @property Grid\Column|Collection email
     * @property Grid\Column|Collection token
     * @property Grid\Column|Collection abilities
     * @property Grid\Column|Collection expires_at
     * @property Grid\Column|Collection last_used_at
     * @property Grid\Column|Collection tokenable_id
     * @property Grid\Column|Collection tokenable_type
     * @property Grid\Column|Collection itemLinkUrl
     * @property Grid\Column|Collection itemPicUrl
     * @property Grid\Column|Collection itemTitle
     * @property Grid\Column|Collection itemType
     * @property Grid\Column|Collection skuDesc
     * @property Grid\Column|Collection access_token
     * @property Grid\Column|Collection expires_in
     * @property Grid\Column|Collection open_id
     * @property Grid\Column|Collection refresh_token
     * @property Grid\Column|Collection refresh_token_expires_in
     * @property Grid\Column|Collection batch_id
     * @property Grid\Column|Collection family_hash
     * @property Grid\Column|Collection sequence
     * @property Grid\Column|Collection should_display_on_index
     * @property Grid\Column|Collection entry_uuid
     * @property Grid\Column|Collection tag
     * @property Grid\Column|Collection email_verified_at
     * @property Grid\Column|Collection response
     * @property Grid\Column|Collection wechat
     * @property Grid\Column|Collection appid
     * @property Grid\Column|Collection message
     * @property Grid\Column|Collection secret
     *
     * @method Grid\Column|Collection created_at(string $label = null)
     * @method Grid\Column|Collection detail(string $label = null)
     * @method Grid\Column|Collection id(string $label = null)
     * @method Grid\Column|Collection name(string $label = null)
     * @method Grid\Column|Collection type(string $label = null)
     * @method Grid\Column|Collection updated_at(string $label = null)
     * @method Grid\Column|Collection version(string $label = null)
     * @method Grid\Column|Collection is_enabled(string $label = null)
     * @method Grid\Column|Collection extension(string $label = null)
     * @method Grid\Column|Collection icon(string $label = null)
     * @method Grid\Column|Collection order(string $label = null)
     * @method Grid\Column|Collection parent_id(string $label = null)
     * @method Grid\Column|Collection uri(string $label = null)
     * @method Grid\Column|Collection menu_id(string $label = null)
     * @method Grid\Column|Collection permission_id(string $label = null)
     * @method Grid\Column|Collection http_method(string $label = null)
     * @method Grid\Column|Collection http_path(string $label = null)
     * @method Grid\Column|Collection slug(string $label = null)
     * @method Grid\Column|Collection role_id(string $label = null)
     * @method Grid\Column|Collection user_id(string $label = null)
     * @method Grid\Column|Collection value(string $label = null)
     * @method Grid\Column|Collection avatar(string $label = null)
     * @method Grid\Column|Collection password(string $label = null)
     * @method Grid\Column|Collection remember_token(string $label = null)
     * @method Grid\Column|Collection username(string $label = null)
     * @method Grid\Column|Collection amount(string $label = null)
     * @method Grid\Column|Collection apply_at(string $label = null)
     * @method Grid\Column|Collection master_id(string $label = null)
     * @method Grid\Column|Collection pay_at(string $label = null)
     * @method Grid\Column|Collection remark(string $label = null)
     * @method Grid\Column|Collection status(string $label = null)
     * @method Grid\Column|Collection uuid(string $label = null)
     * @method Grid\Column|Collection cashed(string $label = null)
     * @method Grid\Column|Collection distributorId(string $label = null)
     * @method Grid\Column|Collection today_can_cash(string $label = null)
     * @method Grid\Column|Collection total_commission(string $label = null)
     * @method Grid\Column|Collection total_order(string $label = null)
     * @method Grid\Column|Collection total_pay(string $label = null)
     * @method Grid\Column|Collection vaild_order(string $label = null)
     * @method Grid\Column|Collection cash(string $label = null)
     * @method Grid\Column|Collection commission1(string $label = null)
     * @method Grid\Column|Collection commission2(string $label = null)
     * @method Grid\Column|Collection date(string $label = null)
     * @method Grid\Column|Collection refund_commission(string $label = null)
     * @method Grid\Column|Collection refund_order(string $label = null)
     * @method Grid\Column|Collection total_cash(string $label = null)
     * @method Grid\Column|Collection total_sale(string $label = null)
     * @method Grid\Column|Collection total_uncommission(string $label = null)
     * @method Grid\Column|Collection distributorName(string $label = null)
     * @method Grid\Column|Collection connection(string $label = null)
     * @method Grid\Column|Collection exception(string $label = null)
     * @method Grid\Column|Collection failed_at(string $label = null)
     * @method Grid\Column|Collection payload(string $label = null)
     * @method Grid\Column|Collection queue(string $label = null)
     * @method Grid\Column|Collection attempts(string $label = null)
     * @method Grid\Column|Collection available_at(string $label = null)
     * @method Grid\Column|Collection reserved_at(string $label = null)
     * @method Grid\Column|Collection account(string $label = null)
     * @method Grid\Column|Collection canCommission(string $label = null)
     * @method Grid\Column|Collection cash_money(string $label = null)
     * @method Grid\Column|Collection commission_money(string $label = null)
     * @method Grid\Column|Collection commissioned(string $label = null)
     * @method Grid\Column|Collection parent_rate(string $label = null)
     * @method Grid\Column|Collection rate1(string $label = null)
     * @method Grid\Column|Collection rate2(string $label = null)
     * @method Grid\Column|Collection realname(string $label = null)
     * @method Grid\Column|Collection wechat_rate1(string $label = null)
     * @method Grid\Column|Collection wechat_rate2(string $label = null)
     * @method Grid\Column|Collection after_money(string $label = null)
     * @method Grid\Column|Collection before_money(string $label = null)
     * @method Grid\Column|Collection content(string $label = null)
     * @method Grid\Column|Collection is_parent(string $label = null)
     * @method Grid\Column|Collection money(string $label = null)
     * @method Grid\Column|Collection oid(string $label = null)
     * @method Grid\Column|Collection rate(string $label = null)
     * @method Grid\Column|Collection messages(string $label = null)
     * @method Grid\Column|Collection order_log_id(string $label = null)
     * @method Grid\Column|Collection fail(string $label = null)
     * @method Grid\Column|Collection failFile(string $label = null)
     * @method Grid\Column|Collection file(string $label = null)
     * @method Grid\Column|Collection sellerNick(string $label = null)
     * @method Grid\Column|Collection sellerOpenId(string $label = null)
     * @method Grid\Column|Collection success(string $label = null)
     * @method Grid\Column|Collection total(string $label = null)
     * @method Grid\Column|Collection unPayOrCps(string $label = null)
     * @method Grid\Column|Collection unSend(string $label = null)
     * @method Grid\Column|Collection allCommission(string $label = null)
     * @method Grid\Column|Collection doneCommission(string $label = null)
     * @method Grid\Column|Collection unCommission(string $label = null)
     * @method Grid\Column|Collection commission1_rate(string $label = null)
     * @method Grid\Column|Collection commission1Time(string $label = null)
     * @method Grid\Column|Collection commission2_rate(string $label = null)
     * @method Grid\Column|Collection commission2Time(string $label = null)
     * @method Grid\Column|Collection commission_rate(string $label = null)
     * @method Grid\Column|Collection commissionRate(string $label = null)
     * @method Grid\Column|Collection commissionTotal(string $label = null)
     * @method Grid\Column|Collection cpsDetail(string $label = null)
     * @method Grid\Column|Collection cpsPayStatus(string $label = null)
     * @method Grid\Column|Collection cpsStatus(string $label = null)
     * @method Grid\Column|Collection cpsType(string $label = null)
     * @method Grid\Column|Collection createTime(string $label = null)
     * @method Grid\Column|Collection discountFee(string $label = null)
     * @method Grid\Column|Collection estimatedIncome(string $label = null)
     * @method Grid\Column|Collection expressFee(string $label = null)
     * @method Grid\Column|Collection goodDiscountFee(string $label = null)
     * @method Grid\Column|Collection importStatus(string $label = null)
     * @method Grid\Column|Collection itemId(string $label = null)
     * @method Grid\Column|Collection log_id(string $label = null)
     * @method Grid\Column|Collection num(string $label = null)
     * @method Grid\Column|Collection orderChannel(string $label = null)
     * @method Grid\Column|Collection orderDetail(string $label = null)
     * @method Grid\Column|Collection originalPrice(string $label = null)
     * @method Grid\Column|Collection parent_commission(string $label = null)
     * @method Grid\Column|Collection parent_commission1(string $label = null)
     * @method Grid\Column|Collection parent_commission2(string $label = null)
     * @method Grid\Column|Collection payTime(string $label = null)
     * @method Grid\Column|Collection platformDpRate(string $label = null)
     * @method Grid\Column|Collection price(string $label = null)
     * @method Grid\Column|Collection promoterExcitationInCome(string $label = null)
     * @method Grid\Column|Collection promoterServiceInCome(string $label = null)
     * @method Grid\Column|Collection refundDetail(string $label = null)
     * @method Grid\Column|Collection refundStatus(string $label = null)
     * @method Grid\Column|Collection refundTime(string $label = null)
     * @method Grid\Column|Collection relItemId(string $label = null)
     * @method Grid\Column|Collection relSkuId(string $label = null)
     * @method Grid\Column|Collection sellerType(string $label = null)
     * @method Grid\Column|Collection sendTime(string $label = null)
     * @method Grid\Column|Collection settlementBizType(string $label = null)
     * @method Grid\Column|Collection skuId(string $label = null)
     * @method Grid\Column|Collection skuNick(string $label = null)
     * @method Grid\Column|Collection totalFee(string $label = null)
     * @method Grid\Column|Collection updateTime(string $label = null)
     * @method Grid\Column|Collection email(string $label = null)
     * @method Grid\Column|Collection token(string $label = null)
     * @method Grid\Column|Collection abilities(string $label = null)
     * @method Grid\Column|Collection expires_at(string $label = null)
     * @method Grid\Column|Collection last_used_at(string $label = null)
     * @method Grid\Column|Collection tokenable_id(string $label = null)
     * @method Grid\Column|Collection tokenable_type(string $label = null)
     * @method Grid\Column|Collection itemLinkUrl(string $label = null)
     * @method Grid\Column|Collection itemPicUrl(string $label = null)
     * @method Grid\Column|Collection itemTitle(string $label = null)
     * @method Grid\Column|Collection itemType(string $label = null)
     * @method Grid\Column|Collection skuDesc(string $label = null)
     * @method Grid\Column|Collection access_token(string $label = null)
     * @method Grid\Column|Collection expires_in(string $label = null)
     * @method Grid\Column|Collection open_id(string $label = null)
     * @method Grid\Column|Collection refresh_token(string $label = null)
     * @method Grid\Column|Collection refresh_token_expires_in(string $label = null)
     * @method Grid\Column|Collection batch_id(string $label = null)
     * @method Grid\Column|Collection family_hash(string $label = null)
     * @method Grid\Column|Collection sequence(string $label = null)
     * @method Grid\Column|Collection should_display_on_index(string $label = null)
     * @method Grid\Column|Collection entry_uuid(string $label = null)
     * @method Grid\Column|Collection tag(string $label = null)
     * @method Grid\Column|Collection email_verified_at(string $label = null)
     * @method Grid\Column|Collection response(string $label = null)
     * @method Grid\Column|Collection wechat(string $label = null)
     * @method Grid\Column|Collection appid(string $label = null)
     * @method Grid\Column|Collection message(string $label = null)
     * @method Grid\Column|Collection secret(string $label = null)
     */
    class Grid {}

    class MiniGrid extends Grid {}

    /**
     * @property Show\Field|Collection created_at
     * @property Show\Field|Collection detail
     * @property Show\Field|Collection id
     * @property Show\Field|Collection name
     * @property Show\Field|Collection type
     * @property Show\Field|Collection updated_at
     * @property Show\Field|Collection version
     * @property Show\Field|Collection is_enabled
     * @property Show\Field|Collection extension
     * @property Show\Field|Collection icon
     * @property Show\Field|Collection order
     * @property Show\Field|Collection parent_id
     * @property Show\Field|Collection uri
     * @property Show\Field|Collection menu_id
     * @property Show\Field|Collection permission_id
     * @property Show\Field|Collection http_method
     * @property Show\Field|Collection http_path
     * @property Show\Field|Collection slug
     * @property Show\Field|Collection role_id
     * @property Show\Field|Collection user_id
     * @property Show\Field|Collection value
     * @property Show\Field|Collection avatar
     * @property Show\Field|Collection password
     * @property Show\Field|Collection remember_token
     * @property Show\Field|Collection username
     * @property Show\Field|Collection amount
     * @property Show\Field|Collection apply_at
     * @property Show\Field|Collection master_id
     * @property Show\Field|Collection pay_at
     * @property Show\Field|Collection remark
     * @property Show\Field|Collection status
     * @property Show\Field|Collection uuid
     * @property Show\Field|Collection cashed
     * @property Show\Field|Collection distributorId
     * @property Show\Field|Collection today_can_cash
     * @property Show\Field|Collection total_commission
     * @property Show\Field|Collection total_order
     * @property Show\Field|Collection total_pay
     * @property Show\Field|Collection vaild_order
     * @property Show\Field|Collection cash
     * @property Show\Field|Collection commission1
     * @property Show\Field|Collection commission2
     * @property Show\Field|Collection date
     * @property Show\Field|Collection refund_commission
     * @property Show\Field|Collection refund_order
     * @property Show\Field|Collection total_cash
     * @property Show\Field|Collection total_sale
     * @property Show\Field|Collection total_uncommission
     * @property Show\Field|Collection distributorName
     * @property Show\Field|Collection connection
     * @property Show\Field|Collection exception
     * @property Show\Field|Collection failed_at
     * @property Show\Field|Collection payload
     * @property Show\Field|Collection queue
     * @property Show\Field|Collection attempts
     * @property Show\Field|Collection available_at
     * @property Show\Field|Collection reserved_at
     * @property Show\Field|Collection account
     * @property Show\Field|Collection canCommission
     * @property Show\Field|Collection cash_money
     * @property Show\Field|Collection commission_money
     * @property Show\Field|Collection commissioned
     * @property Show\Field|Collection parent_rate
     * @property Show\Field|Collection rate1
     * @property Show\Field|Collection rate2
     * @property Show\Field|Collection realname
     * @property Show\Field|Collection wechat_rate1
     * @property Show\Field|Collection wechat_rate2
     * @property Show\Field|Collection after_money
     * @property Show\Field|Collection before_money
     * @property Show\Field|Collection content
     * @property Show\Field|Collection is_parent
     * @property Show\Field|Collection money
     * @property Show\Field|Collection oid
     * @property Show\Field|Collection rate
     * @property Show\Field|Collection messages
     * @property Show\Field|Collection order_log_id
     * @property Show\Field|Collection fail
     * @property Show\Field|Collection failFile
     * @property Show\Field|Collection file
     * @property Show\Field|Collection sellerNick
     * @property Show\Field|Collection sellerOpenId
     * @property Show\Field|Collection success
     * @property Show\Field|Collection total
     * @property Show\Field|Collection unPayOrCps
     * @property Show\Field|Collection unSend
     * @property Show\Field|Collection allCommission
     * @property Show\Field|Collection doneCommission
     * @property Show\Field|Collection unCommission
     * @property Show\Field|Collection commission1_rate
     * @property Show\Field|Collection commission1Time
     * @property Show\Field|Collection commission2_rate
     * @property Show\Field|Collection commission2Time
     * @property Show\Field|Collection commission_rate
     * @property Show\Field|Collection commissionRate
     * @property Show\Field|Collection commissionTotal
     * @property Show\Field|Collection cpsDetail
     * @property Show\Field|Collection cpsPayStatus
     * @property Show\Field|Collection cpsStatus
     * @property Show\Field|Collection cpsType
     * @property Show\Field|Collection createTime
     * @property Show\Field|Collection discountFee
     * @property Show\Field|Collection estimatedIncome
     * @property Show\Field|Collection expressFee
     * @property Show\Field|Collection goodDiscountFee
     * @property Show\Field|Collection importStatus
     * @property Show\Field|Collection itemId
     * @property Show\Field|Collection log_id
     * @property Show\Field|Collection num
     * @property Show\Field|Collection orderChannel
     * @property Show\Field|Collection orderDetail
     * @property Show\Field|Collection originalPrice
     * @property Show\Field|Collection parent_commission
     * @property Show\Field|Collection parent_commission1
     * @property Show\Field|Collection parent_commission2
     * @property Show\Field|Collection payTime
     * @property Show\Field|Collection platformDpRate
     * @property Show\Field|Collection price
     * @property Show\Field|Collection promoterExcitationInCome
     * @property Show\Field|Collection promoterServiceInCome
     * @property Show\Field|Collection refundDetail
     * @property Show\Field|Collection refundStatus
     * @property Show\Field|Collection refundTime
     * @property Show\Field|Collection relItemId
     * @property Show\Field|Collection relSkuId
     * @property Show\Field|Collection sellerType
     * @property Show\Field|Collection sendTime
     * @property Show\Field|Collection settlementBizType
     * @property Show\Field|Collection skuId
     * @property Show\Field|Collection skuNick
     * @property Show\Field|Collection totalFee
     * @property Show\Field|Collection updateTime
     * @property Show\Field|Collection email
     * @property Show\Field|Collection token
     * @property Show\Field|Collection abilities
     * @property Show\Field|Collection expires_at
     * @property Show\Field|Collection last_used_at
     * @property Show\Field|Collection tokenable_id
     * @property Show\Field|Collection tokenable_type
     * @property Show\Field|Collection itemLinkUrl
     * @property Show\Field|Collection itemPicUrl
     * @property Show\Field|Collection itemTitle
     * @property Show\Field|Collection itemType
     * @property Show\Field|Collection skuDesc
     * @property Show\Field|Collection access_token
     * @property Show\Field|Collection expires_in
     * @property Show\Field|Collection open_id
     * @property Show\Field|Collection refresh_token
     * @property Show\Field|Collection refresh_token_expires_in
     * @property Show\Field|Collection batch_id
     * @property Show\Field|Collection family_hash
     * @property Show\Field|Collection sequence
     * @property Show\Field|Collection should_display_on_index
     * @property Show\Field|Collection entry_uuid
     * @property Show\Field|Collection tag
     * @property Show\Field|Collection email_verified_at
     * @property Show\Field|Collection response
     * @property Show\Field|Collection wechat
     * @property Show\Field|Collection appid
     * @property Show\Field|Collection message
     * @property Show\Field|Collection secret
     *
     * @method Show\Field|Collection created_at(string $label = null)
     * @method Show\Field|Collection detail(string $label = null)
     * @method Show\Field|Collection id(string $label = null)
     * @method Show\Field|Collection name(string $label = null)
     * @method Show\Field|Collection type(string $label = null)
     * @method Show\Field|Collection updated_at(string $label = null)
     * @method Show\Field|Collection version(string $label = null)
     * @method Show\Field|Collection is_enabled(string $label = null)
     * @method Show\Field|Collection extension(string $label = null)
     * @method Show\Field|Collection icon(string $label = null)
     * @method Show\Field|Collection order(string $label = null)
     * @method Show\Field|Collection parent_id(string $label = null)
     * @method Show\Field|Collection uri(string $label = null)
     * @method Show\Field|Collection menu_id(string $label = null)
     * @method Show\Field|Collection permission_id(string $label = null)
     * @method Show\Field|Collection http_method(string $label = null)
     * @method Show\Field|Collection http_path(string $label = null)
     * @method Show\Field|Collection slug(string $label = null)
     * @method Show\Field|Collection role_id(string $label = null)
     * @method Show\Field|Collection user_id(string $label = null)
     * @method Show\Field|Collection value(string $label = null)
     * @method Show\Field|Collection avatar(string $label = null)
     * @method Show\Field|Collection password(string $label = null)
     * @method Show\Field|Collection remember_token(string $label = null)
     * @method Show\Field|Collection username(string $label = null)
     * @method Show\Field|Collection amount(string $label = null)
     * @method Show\Field|Collection apply_at(string $label = null)
     * @method Show\Field|Collection master_id(string $label = null)
     * @method Show\Field|Collection pay_at(string $label = null)
     * @method Show\Field|Collection remark(string $label = null)
     * @method Show\Field|Collection status(string $label = null)
     * @method Show\Field|Collection uuid(string $label = null)
     * @method Show\Field|Collection cashed(string $label = null)
     * @method Show\Field|Collection distributorId(string $label = null)
     * @method Show\Field|Collection today_can_cash(string $label = null)
     * @method Show\Field|Collection total_commission(string $label = null)
     * @method Show\Field|Collection total_order(string $label = null)
     * @method Show\Field|Collection total_pay(string $label = null)
     * @method Show\Field|Collection vaild_order(string $label = null)
     * @method Show\Field|Collection cash(string $label = null)
     * @method Show\Field|Collection commission1(string $label = null)
     * @method Show\Field|Collection commission2(string $label = null)
     * @method Show\Field|Collection date(string $label = null)
     * @method Show\Field|Collection refund_commission(string $label = null)
     * @method Show\Field|Collection refund_order(string $label = null)
     * @method Show\Field|Collection total_cash(string $label = null)
     * @method Show\Field|Collection total_sale(string $label = null)
     * @method Show\Field|Collection total_uncommission(string $label = null)
     * @method Show\Field|Collection distributorName(string $label = null)
     * @method Show\Field|Collection connection(string $label = null)
     * @method Show\Field|Collection exception(string $label = null)
     * @method Show\Field|Collection failed_at(string $label = null)
     * @method Show\Field|Collection payload(string $label = null)
     * @method Show\Field|Collection queue(string $label = null)
     * @method Show\Field|Collection attempts(string $label = null)
     * @method Show\Field|Collection available_at(string $label = null)
     * @method Show\Field|Collection reserved_at(string $label = null)
     * @method Show\Field|Collection account(string $label = null)
     * @method Show\Field|Collection canCommission(string $label = null)
     * @method Show\Field|Collection cash_money(string $label = null)
     * @method Show\Field|Collection commission_money(string $label = null)
     * @method Show\Field|Collection commissioned(string $label = null)
     * @method Show\Field|Collection parent_rate(string $label = null)
     * @method Show\Field|Collection rate1(string $label = null)
     * @method Show\Field|Collection rate2(string $label = null)
     * @method Show\Field|Collection realname(string $label = null)
     * @method Show\Field|Collection wechat_rate1(string $label = null)
     * @method Show\Field|Collection wechat_rate2(string $label = null)
     * @method Show\Field|Collection after_money(string $label = null)
     * @method Show\Field|Collection before_money(string $label = null)
     * @method Show\Field|Collection content(string $label = null)
     * @method Show\Field|Collection is_parent(string $label = null)
     * @method Show\Field|Collection money(string $label = null)
     * @method Show\Field|Collection oid(string $label = null)
     * @method Show\Field|Collection rate(string $label = null)
     * @method Show\Field|Collection messages(string $label = null)
     * @method Show\Field|Collection order_log_id(string $label = null)
     * @method Show\Field|Collection fail(string $label = null)
     * @method Show\Field|Collection failFile(string $label = null)
     * @method Show\Field|Collection file(string $label = null)
     * @method Show\Field|Collection sellerNick(string $label = null)
     * @method Show\Field|Collection sellerOpenId(string $label = null)
     * @method Show\Field|Collection success(string $label = null)
     * @method Show\Field|Collection total(string $label = null)
     * @method Show\Field|Collection unPayOrCps(string $label = null)
     * @method Show\Field|Collection unSend(string $label = null)
     * @method Show\Field|Collection allCommission(string $label = null)
     * @method Show\Field|Collection doneCommission(string $label = null)
     * @method Show\Field|Collection unCommission(string $label = null)
     * @method Show\Field|Collection commission1_rate(string $label = null)
     * @method Show\Field|Collection commission1Time(string $label = null)
     * @method Show\Field|Collection commission2_rate(string $label = null)
     * @method Show\Field|Collection commission2Time(string $label = null)
     * @method Show\Field|Collection commission_rate(string $label = null)
     * @method Show\Field|Collection commissionRate(string $label = null)
     * @method Show\Field|Collection commissionTotal(string $label = null)
     * @method Show\Field|Collection cpsDetail(string $label = null)
     * @method Show\Field|Collection cpsPayStatus(string $label = null)
     * @method Show\Field|Collection cpsStatus(string $label = null)
     * @method Show\Field|Collection cpsType(string $label = null)
     * @method Show\Field|Collection createTime(string $label = null)
     * @method Show\Field|Collection discountFee(string $label = null)
     * @method Show\Field|Collection estimatedIncome(string $label = null)
     * @method Show\Field|Collection expressFee(string $label = null)
     * @method Show\Field|Collection goodDiscountFee(string $label = null)
     * @method Show\Field|Collection importStatus(string $label = null)
     * @method Show\Field|Collection itemId(string $label = null)
     * @method Show\Field|Collection log_id(string $label = null)
     * @method Show\Field|Collection num(string $label = null)
     * @method Show\Field|Collection orderChannel(string $label = null)
     * @method Show\Field|Collection orderDetail(string $label = null)
     * @method Show\Field|Collection originalPrice(string $label = null)
     * @method Show\Field|Collection parent_commission(string $label = null)
     * @method Show\Field|Collection parent_commission1(string $label = null)
     * @method Show\Field|Collection parent_commission2(string $label = null)
     * @method Show\Field|Collection payTime(string $label = null)
     * @method Show\Field|Collection platformDpRate(string $label = null)
     * @method Show\Field|Collection price(string $label = null)
     * @method Show\Field|Collection promoterExcitationInCome(string $label = null)
     * @method Show\Field|Collection promoterServiceInCome(string $label = null)
     * @method Show\Field|Collection refundDetail(string $label = null)
     * @method Show\Field|Collection refundStatus(string $label = null)
     * @method Show\Field|Collection refundTime(string $label = null)
     * @method Show\Field|Collection relItemId(string $label = null)
     * @method Show\Field|Collection relSkuId(string $label = null)
     * @method Show\Field|Collection sellerType(string $label = null)
     * @method Show\Field|Collection sendTime(string $label = null)
     * @method Show\Field|Collection settlementBizType(string $label = null)
     * @method Show\Field|Collection skuId(string $label = null)
     * @method Show\Field|Collection skuNick(string $label = null)
     * @method Show\Field|Collection totalFee(string $label = null)
     * @method Show\Field|Collection updateTime(string $label = null)
     * @method Show\Field|Collection email(string $label = null)
     * @method Show\Field|Collection token(string $label = null)
     * @method Show\Field|Collection abilities(string $label = null)
     * @method Show\Field|Collection expires_at(string $label = null)
     * @method Show\Field|Collection last_used_at(string $label = null)
     * @method Show\Field|Collection tokenable_id(string $label = null)
     * @method Show\Field|Collection tokenable_type(string $label = null)
     * @method Show\Field|Collection itemLinkUrl(string $label = null)
     * @method Show\Field|Collection itemPicUrl(string $label = null)
     * @method Show\Field|Collection itemTitle(string $label = null)
     * @method Show\Field|Collection itemType(string $label = null)
     * @method Show\Field|Collection skuDesc(string $label = null)
     * @method Show\Field|Collection access_token(string $label = null)
     * @method Show\Field|Collection expires_in(string $label = null)
     * @method Show\Field|Collection open_id(string $label = null)
     * @method Show\Field|Collection refresh_token(string $label = null)
     * @method Show\Field|Collection refresh_token_expires_in(string $label = null)
     * @method Show\Field|Collection batch_id(string $label = null)
     * @method Show\Field|Collection family_hash(string $label = null)
     * @method Show\Field|Collection sequence(string $label = null)
     * @method Show\Field|Collection should_display_on_index(string $label = null)
     * @method Show\Field|Collection entry_uuid(string $label = null)
     * @method Show\Field|Collection tag(string $label = null)
     * @method Show\Field|Collection email_verified_at(string $label = null)
     * @method Show\Field|Collection response(string $label = null)
     * @method Show\Field|Collection wechat(string $label = null)
     * @method Show\Field|Collection appid(string $label = null)
     * @method Show\Field|Collection message(string $label = null)
     * @method Show\Field|Collection secret(string $label = null)
     */
    class Show {}

    /**
     
     */
    class Form {}

}

namespace Dcat\Admin\Grid {
    /**
     
     */
    class Column {}

    /**
     * @method \App\Admin\Grid\Filter\Between myCustomBetween(...$params)
     */
    class Filter {}
}

namespace Dcat\Admin\Show {
    /**
     
     */
    class Field {}
}
