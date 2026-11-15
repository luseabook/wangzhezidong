<?php

namespace App\Console\Commands;

use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use Illuminate\Console\Command;

class ShowOrderCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:show-order-commission {oid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查看订单的佣金';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Money::truncate();
            $this->info('已清空流水记录记录');
            Order::query()->update([
                "importStatus" => 0,
                "cpsPayStatus" => 0,
                "commissionTotal" => 0,
                "commission1" => 0,
                "commission1Time" => null,
                "commission2" => 0,
                "commission2Time" => null,
            ]);
            $this->info('订单相关字段已修改');
            Master::query()->update([
                "commission_money" => 0,
            ]);
            $this->info('达人相关字段已修改');

        $this->info("开始计算预估总佣金");
        $total = 0;
        Order::query()
            ->with("product")
            ->ofCommission()
            ->where("commissionTotal", 0)
            ->chunk(1000, function($orders) use (&$total) {
                foreach ($orders as $order) {
                    if($order->oid == '2336100034419893') {
                        $this->error(intval(($order->totalFee - $order->expressFee) * $order->rate / 100));
                    }
                    $order->commissionTotal = intval(($order->totalFee - $order->expressFee) * $order->rate / 100);
                    $order->save();

                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束计算预估总佣金");
        return;

        $oid = $this->argument('oid');
        if(!$oid) {
            $this->error('订单号不能为空');
            return;
        }

        $order = Order::query()
            ->with("product")
            ->where("oid", $oid)
            ->first();

        if(!$order) {
            $this->error("订单不存在");
            return;   
        }

        $commissionTotal = ($order->totalFee - $order->expressFee) * $order->rate / 100;
        $commission1 = ($order->totalFee - $order->expressFee) * $order->rate1 / 100;
        $commission2 = $commissionTotal - $commission1;
        
        $this->info("订单号: {$order->oid}");
        $this->info("预估佣金: " . $commissionTotal / 100);
        $this->info("第一次结算: " . $commission1 / 100);
        $this->info("第二次结算: " . $commission2 / 100);

        $order = Order::query()
            ->with("product")
            ->ofCommission()
            ->where("commission1", 0)
            ->where("sendTime", "<=", now()->subHours(admin_setting("commission1_days", 72)))
            ->where("oid", $oid)
            ->first();

        if(!$order) {
            $this->error("第一次结算条件不满足");
        }
    }
}
