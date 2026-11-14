<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class UpdateOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:update-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '依据现有数据更新orders表字段';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始更新orders表字段');
        Order::query()
            ->chunk(100, function($orders) {
                foreach ($orders as $order) {
                    $detail = json_decode($order->orderDetail, true);

                    $order->num = $detail["orderItemInfo"]["num"];
                    $order->refundStatus = (isset($detail["orderRefundList"]) && isset($detail["orderRefundList"]["refundStatus"])) ? $detail["orderRefundList"]["refundStatus"] : 0;
                    $order->save();
                }
            });

        $this->info('更新完成');
    }
}
