<?php

namespace App\Models;

/**
 * 订单字段变更历史
 *
 * 不启用 timestamps（用 changed_at 表达时间），避免与 dcat 的日期格式化冲突。
 */
class OrderHistory extends Base
{
    protected $table = 'order_histories';

    public $timestamps = false;

    protected $casts = [
        'oid'          => 'integer',
        'order_id'     => 'integer',
        'order_log_id' => 'integer',
        'source'       => 'integer',
        'changed_at'   => 'datetime',
    ];

    public const SOURCE = [
        0 => '手工',
        1 => '自动导入',
        2 => '系统任务',
    ];

    /** 字段中文名，后台展示用 */
    public const FIELD_LABEL = [
        '__created'        => '订单创建',
        'status'           => '订单状态',
        'refundStatus'     => '退款状态',
        'sendTime'         => '发货时间',
        'payTime'          => '支付时间',
        'totalFee'         => '订单金额',
        'expressFee'       => '运费',
        'discountFee'      => '优惠金额',
        'commissionRate'   => '佣金比例',
        'estimatedIncome'  => '预估佣金',
        'distributorId'    => '达人ID',
        'distributorName'  => '达人昵称',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderLog()
    {
        return $this->belongsTo(OrderLog::class, 'order_log_id');
    }

    public function fieldLabel(): string
    {
        return self::FIELD_LABEL[$this->field] ?? $this->field;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE[$this->source] ?? (string) $this->source;
    }
}