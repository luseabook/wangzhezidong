<?php

namespace App\Models;

class OrderLog extends Base
{
    public static $status = [
        0 => "待处理",
        4 => "导入中",
        5 => "已导入",
        6 => "导入失败",
        9 => "处理中",
        10 => "已处理",
        11 => "处理失败",
        15 => "已删除",
    ];

    public static $type = [
        0 => "快手",
        1 => "视频号",
    ];
}
