<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class UpdateOrderCommissionTotal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:update-order-commissionTotal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修正预估总佣金出现问题的订单';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("已废弃");
        return;

        $this->info("开始修正预估总佣金出现问题的订单");
        $total = 0;
        Order::query()
            ->ofCommission()
            ->where("commissionTotal", '<>', 0)
            ->chunk(1000, function($orders) use (&$total) {
                foreach ($orders as $order) {
                    $commissionTotal = ($order->totalFee - $order->expressFee) * $order->rate / 100;
                    if($order->commissionTotal != round($commissionTotal)) {
                        $order->commissionTotal = $commissionTotal;
                        $order->save();
    
                        $total += 1;
                    }
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束修正预估总佣金出现问题的订单");
    }
}
