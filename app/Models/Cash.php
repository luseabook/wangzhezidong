<?php

namespace App\Models;

use App\Traits\CashTrait;

class Cash extends Base
{
    use CashTrait;

    public static $status = [
        0 => "待审核",
        1 => "确认",
        2 => "驳回"
    ];

   /**
     * 提现属于一位达人
     */
    public function master()
    {
        return $this->belongsTo(Master::class, "master_id", "id");
    }
}
