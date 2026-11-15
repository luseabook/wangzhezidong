<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Services\OrderSettlementReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * 商品佣金率变更后的结算重算。
 *
 * 把该商品下未售后、已计算/已结算的订单按实际账本净额冲回，
 * 重置佣金/上级/团长/结算状态，再交给主命令按新费率完整重结。
 */
class OrderCommissionByProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function handle(): void
    {
        $count = 0;
        $resetter = app(OrderSettlementReset::class);

        Order::query()
            ->where("itemId", $this->product->itemId)
            ->whereIn("cpsPayStatus", [0, 1, 2])
            ->whereIn("refundStatus", [0, 70])
            ->chunkById(500, function ($orders) use (&$count, $resetter) {
                foreach ($orders as $order) {
                    $resetter->reverseMoney($order, "商品改率冲正");
                    $resetter->reset($order);
                    $count += 1;
                }
            });

        Log::info("[order-commission-by-product] reset {$count} orders", [
            "itemId" => $this->product->itemId,
            "commissionRate" => $this->product->commissionRate,
        ]);

        // 主命令按数据库里的最新商品费率重新计算并结算这些订单。
        Artisan::queue("create:order-commission");
    }
}