<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

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
     * 总分佣比例
     *
     * @return int
     */
    public function getRateAttribute()
    {
        if(!$this->product || !$this->product->commissionRate) return 0;
        
        if(in_array($this->status, [0, 10, 80])) return 0;

        if($this->product->master_id != 0 && $this->product->leader_rate > 0) {
            return ($this->product->commissionRate - 1 - $this->product->leader_rate);
        }

        return ($this->product->commissionRate-1);
    }

    /**
     * 团长分佣比例
     */
    public function getLeaderRateAttribute()
    {
        if($this->product && $this->product->master_id != 0 && $this->product->leader_rate > 0) {
            return $this->product->leader_rate;
        }

        return 0;
    }

    public function getLeaderRate1Attribute()
    {
        if($this->product && $this->product->master_id != 0 && $this->product->leader_rate > 0) {
            // 视频号
            if($this->sellerType) {
                // 设置了结算比例
                if($this->distributor && $this->distributor->master && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2))
                {
                    return $this->product->leader_rate * $this->distributor->master->wechat_rate1 / 100;
                }

                return $this->product->leader_rate * admin_setting('wechat_commission1_rate', 50) / 100;
            }

            // 设置了结算比例
            if($this->distributor && $this->distributor->master && ($this->distributor->master->rate1 + $this->distributor->master->rate2))
            {
                return $this->product->leader_rate * $this->distributor->master->rate1 / 100;
            }

            return $this->product->leader_rate * admin_setting('commission1_rate', 50) / 100;
        }

        return 0;
    }

    public function getLeaderRate2Attribute()
    {
        if($this->product && $this->product->master_id != 0 && $this->product->leader_rate > 0) {
            // 视频号
            if($this->sellerType) {
                // 设置了结算比例
                if($this->distributor && $this->distributor->master && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2))
                {
                    return $this->product->leader_rate * $this->distributor->master->wechat_rate2 / 100;
                }

                return $this->product->leader_rate * admin_setting('wechat_commission2_rate', 50) / 100;
            }

            // 设置了结算比例
            if($this->distributor && $this->distributor->master && ($this->distributor->master->rate1 + $this->distributor->master->rate2))
            {
                return $this->product->leader_rate * $this->distributor->master->rate2 / 100;
            }

            return $this->product->leader_rate * admin_setting('commission2_rate', 50) / 100;
        }

        return 0;
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

        // 视频号
        if($this->sellerType) {
            // 设置了结算比例
            if($this->distributor && $this->distributor->master && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2))
            {
                return ($this->product->commissionRate-1-$this->product->leader_rate) * $this->distributor->master->wechat_rate1 / 100;
            }

            return ($this->product->commissionRate-1-$this->product->leader_rate) * admin_setting('wechat_commission1_rate', 50) / 100;
        }

        // 设置了结算比例
        if($this->distributor && $this->distributor->master && ($this->distributor->master->rate1 + $this->distributor->master->rate2))
        {
            return ($this->product->commissionRate-1-$this->product->leader_rate) * $this->distributor->master->rate1 / 100;
        }

        return ($this->product->commissionRate-1-$this->product->leader_rate) * admin_setting('commission1_rate', 50) / 100;
        
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

        // 视频号
        if($this->sellerType) {
            // 设置了结算比例
            if($this->distributor && $this->distributor->master && ($this->distributor->master->wechat_rate1 + $this->distributor->master->wechat_rate2))
            {
                return ($this->product->commissionRate-1-$this->product->leader_rate) * $this->distributor->master->wechat_rate2 / 100;
            }

            return ($this->product->commissionRate-1-$this->product->leader_rate) * admin_setting('wechat_commission2_rate', 50) / 100;
        }

        // 设置了结算比例
        if($this->distributor && $this->distributor->master && ($this->distributor->master->rate1 + $this->distributor->master->rate2))
        {
            return ($this->product->commissionRate-1-$this->product->leader_rate) * $this->distributor->master->rate2 / 100;
        }

        return ($this->product->commissionRate-1-$this->product->leader_rate) * admin_setting('commission2_rate', 50) / 100;
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
