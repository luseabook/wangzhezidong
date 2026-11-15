<?php

namespace App\Models;

class ChartMaster extends Base
{
    /**
     * 明细属于一位达人
     */
    public function master()
    {
        return $this->belongsTo(Master::class, "master_id", "id");
    }

    /**
     * chart_masters 的佣金字段以「分」存储，cash 以「元」存储。
     * 下面几个 accessor 只负责把展示值统一成「元」，不改库里的存储单位。
     */
    public function getCommission1YuanAttribute()
    {
        return number_format(((int) $this->commission1) / 100, 2, '.', '');
    }

    public function getCommission2YuanAttribute()
    {
        return number_format(((int) $this->commission2) / 100, 2, '.', '');
    }

    public function getRefundCommissionYuanAttribute()
    {
        return number_format(((int) $this->refund_commission) / 100, 2, '.', '');
    }

    public function getCashYuanAttribute()
    {
        return number_format((float) $this->cash, 2, '.', '');
    }
}
