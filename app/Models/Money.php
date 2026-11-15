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
     * 是否团长分佣。
     * 上级抽成与团长分佣都写 is_parent=1，靠 is_leader 区分：
     * 1=团长分佣，0=上级抽成/普通分佣。
     */
    public static $is_leader = [
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
