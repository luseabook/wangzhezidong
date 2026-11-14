<?php

namespace App\Models;

class Money extends Base
{
    public static $type = [
        1 => "增加",
        2 => "减少"
    ];

    public static $is_parent = [
        0 => "否",
        1 => "是",
    ];

    /**
     * 明细属于一位达人
     */
    public function master()
    {
        return $this->belongsTo(Master::class, "master_id", "id");
    }
}
