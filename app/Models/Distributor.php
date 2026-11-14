<?php

namespace App\Models;

class Distributor extends Base
{
    public static $status = [
        0 => "待审核",
        1 => "审核通过",
        2 => "审核未通过"
    ];

    public static $type = [
        0 => "快手",
        1 => "视频号",
    ];

    /**
     * 分销者属于一位达人
     */
    public function master()
    {
        return $this->belongsTo(Master::class, "master_id", "id");
    }
}
