<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * 订单。
 *
 * importStatus：导入批次内处理标记（0 待处理 / 1 第一次结算完成 / 2 第二次结算完成），
 * 每次导入会重置为 0；结算阶段请以 cpsPayStatus 为准，首/尾笔不再用它做闸门。
 */
class Order extends Base
{
    public static $status = [
        0 => "未知状态",
        10 => "待付款",
        30 => "待发货", // 已付款
        40 => "已发货",
        50 => "已签收",
        70 => "订单成功",
        80 => "订单失败",
    ];

    public static $refundStatus = [
        0 => "正常订单",
        10 => "买家仅退款申请",
        11 => "买家仅退款申请",
        20 => "平台介入-买家仅退款申请",
        21 => "平台介入-买家退货退款申请",
        22 => "平台介入-已确认退货退款",
        25 => "待平台判定",
        30 => "商品回寄信息待买家更新",
        40 => "商品回寄信息待卖家确认",
        45 => "待买家确认收货",
        50 => "退款执行中",
        60 => "退款成功",
        70 => "退款失败",
        80 => "待买家处理",
        85 => "待买家退货",
        90 => "待卖家处理",
    ];

    public static $cpsStatus = [
        0 => "未知状态",
        30 => "已付款",
        50 => "已收货",
        60 => "已结算",
        80 => "已失效",
    ];

    public static $cpsPayStatus = [
        0 => "待结算",
        1 => "第一次已结算",
        2 => "第二次已结算",
        3 => "售后",
        4 => "订单关闭",
    ];

    public static $isRefund = [
        0 => "否",
        1 => "是"
    ];

    public static $isRate = [
        0 => "否",
        1 => "是"
    ];

    public static $sellerType = [
        0 => "快手",
        1 => "视频号",
    ];

    protected $appends = [
        "rate",
        "rate1",
        "rate2",
        "leader_rate",
        "leader_rate1",
        "leader_rate2",
        "is_rate",
        "cash_status",
    ];

    /**
     * 订单属于一位达人ID
     */
    public function distributor()
    {
        return $this->belongsTo(Distributor::class, "distributorId", "distributorId");
    }

    /**
     * 实际生效的团长比例：只有商品绑定了团长（master_id != 0）才扣 leader_rate。
     * 避免“填了团长比例但没选团长”导致首/尾笔拆分少算 L。
     */
    public function getEffectiveLeaderRateAttribute()
    {
        if(!$this->product || (int) $this->product->master_id === 0) {
            return 0;
        }

        return (int) $this->product->leader_rate;
    }

    /**
     * 总分佣比例
     *
     * @return int
     */
    public function getRateAttribute()
    {
        if(!$this->product || !$this->product->commissionRate) return 0;

        if(in_array($this->status, [0, 10, 80])) return 0;

        return ($this->product->commissionRate - 1 - $this->effective_leader_rate);
    }

    /**
     * 团长分佣比例
     */
    public function getLeaderRateAttribute()
    {
        return $this->effective_leader_rate;
    }

    /**
     * 当前订单渠道对应的首笔结算比例（百分数）。
     * 视频号用 wechat_rate1 / wechat_commission1_rate，快手用 rate1 / commission1_rate。
     */
    public function getStage1PercentAttribute()
    {
        if($this->sellerType) {
            if($this->distributor && $this->distributor->master
                && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2)) {
                return $this->distributor->master->wechat_rate1;
            }

            return admin_setting('wechat_commission1_rate', 50);
        }

        if($this->distributor && $this->distributor->master
            && ($this->distributor->master->rate1 + $this->distributor->master->rate2)) {
            return $this->distributor->master->rate1;
        }

        return admin_setting('commission1_rate', 50);
    }

    /**
     * 当前订单渠道对应的尾笔结算比例（百分数）。
     */
    public function getStage2PercentAttribute()
    {
        if($this->sellerType) {
            if($this->distributor && $this->distributor->master
                && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2)) {
                return $this->distributor->master->wechat_rate2;
            }

            return admin_setting('wechat_commission2_rate', 50);
        }

        if($this->distributor && $this->distributor->master
            && ($this->distributor->master->rate1 + $this->distributor->master->rate2)) {
            return $this->distributor->master->rate2;
        }

        return admin_setting('commission2_rate', 50);
    }

    public function getLeaderRate1Attribute()
    {
        return $this->effective_leader_rate * $this->stage1_percent / 100;
    }

    public function getLeaderRate2Attribute()
    {
        return $this->effective_leader_rate * $this->stage2_percent / 100;
    }

    /**
     * 第一次分佣比例
     *
     * @return int
     */
    public function getRate1Attribute()
    {
        if(!$this->product || !$this->product->commissionRate) return 0;

        if(in_array($this->status, [0, 10, 80])) return 0;

        return ($this->product->commissionRate - 1 - $this->effective_leader_rate) * $this->stage1_percent / 100;
    }

    /**
     * 第二次分佣比例
     *
     * @return int
     */
    public function getRate2Attribute()
    {
        if(!$this->product || !$this->product->commissionRate) return 0;

        if(in_array($this->status, [0, 10, 80])) return 0;

        return ($this->product->commissionRate - 1 - $this->effective_leader_rate) * $this->stage2_percent / 100;
    }

    /**
     * 是否参与分佣
     *
     * @return int
     */
    public function getIsRateAttribute()
    {
        // 没有关联的商品或者商品未设置分佣
        if(!$this->product || !$this->product->commissionRate) return 0;

        // 没有发货时间：结算范围不会命中（scopeOfCommission 同样要求 sendTime 非空）
        if(!$this->sendTime) return 0;

        // 订单状态
        if(!in_array($this->status, [30, 40, 50, 70])) return 0;

        if(!in_array($this->refundStatus, [0, 70])) return 0;

        return 1;
    }

    /**
     * 第一次分佣金额
     *
     * @return int
     */
    public function getRate1PriceAttribute()
    {
        return sprintf("%.2f", $this->commissionTotal / 100 * $this->rate1);
    }

    /**
     * 第二次分佣金额
     *
     * @return int
     */
    public function getRate2PriceAttribute()
    {
        return sprintf("%.2f", $this->commissionTotal / 100 * $this->rate2);
    }

    /**
     * 结算状态
     * cashStatus
     */
    public function getCashStatusAttribute()
    {
        // 没有关联的商品或者商品未设置分佣
        if(!$this->product || !$this->product->commissionRate) return "";

        return self::$cpsPayStatus[$this->cpsPayStatus];
    }

    public function product()
    {
        return $this->hasOne(Product::class, "itemId", "itemId");
    }

    /**
     * 导入批次（用于区分手动/自动来源）
     */
    public function log()
    {
        return $this->belongsTo(OrderLog::class, "log_id");
    }

    /**
     * 参与分佣的订单
     */
    public function scopeOfCommission($query, $status=[30, 40, 50, 70])
    {
        $query->whereIn("refundStatus", [0, 70])
            ->whereIn("status", $status)
            ->whereNotNull("sendTime")
            ->whereHasIn('product', function ($query) {
                $query->where('commissionRate', ">", 0);
            });
    }

    /**
     * 售后订单
     */
    public function scopeOfRefund($query)
    {
        $query->whereNotIn("refundStatus", [0, 70]);
    }

    /**
     * 不是售后订单
     */
    public function scopeOfNotRefund($query)
    {
        $query->whereIn("refundStatus", [0, 70]);
    }

    /**
     * 模型的「引导」方法。
     */
    protected static function booted(): void
    {
        static::addGlobalScope('settingRate', function (Builder $builder) {
            $builder->with(['distributor' => function($query) {
                $query->with("master");
            }]);
        });
    }
}
