<?php

namespace App\Models;

use App\Jobs\OrderCommissionByProduct;

class Product extends Base
{
    public static $type = [
        0 => "快手",
        1 => "视频号",
    ];
    
    public static $status = [
        0 => "否",
        1 => "是",
    ];

    public static $itemType = [
        1 => "自建商品",
        2 => "闪电购商品",
    ];

    public function master()
    {
        return $this->belongsTo(Master::class, 'master_id', 'id');
    }

    /**
     * 模型的「booted」方法。
     */
    protected static function booted(): void
    {
        static::updated(function (Product $product) {
            // 修改了产品的佣金比例
            if ($product->isDirty('commissionRate')) {
                OrderCommissionByProduct::dispatch($product);
            }
        });
    }
}
