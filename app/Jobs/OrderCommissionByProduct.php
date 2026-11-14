<?php

namespace App\Jobs;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderCommissionByProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->commisson();

        $distributorIds = Order::query()
                ->where("itemId", $this->product->itemId)
                ->get()
                ->pluck("distributorId")
                ->toArray();

        Master::query()
            ->whereHas("distributors", function ($query) use ($distributorIds) {
                $query->whereIn("distributorId", $distributorIds);
            })
            ->get()
            ->each(function ($master) {
                $distributor_ids = Distributor::query()
                    ->where("master_id", $master->id)
                    ->where("status", 1)
                    ->get()
                    ->pluck("distributorId")
                    ->toArray();

                $this->firstCommisson($master, $distributor_ids);
                $this->secondCommisson($master, $distributor_ids);
                $this->refund($master, $distributor_ids);
            });
        
    }

    /**
     * 计算预估总佣金
     */
    private function commisson()
    {
        Order::query()
            ->with("product")
            ->ofCommission()
            ->where("itemId", $this->product->itemId)
            ->where("commissionTotal", "<>", 0)
            ->chunk(1000, function($orders) {
                foreach ($orders as $order) {
                    $order->commissionTotal = intval(($order->totalFee - $order->expressFee) * $order->rate / 100);
                    $order->save();
                }
            });
    }

    private function firstCommisson($master, $distributor_ids)
    {
        Order::query()
            ->with("product")
            ->where("itemId", $this->product->itemId)
            ->whereIn("distributorId", $distributor_ids)
            ->ofCommission()
            ->where("commission1", "<>", 0)
            ->where("cpsPayStatus", 1)
            ->where("payTime", "<=", now()->subHours(admin_setting("commission1_days", 72)))
            ->chunk(1000, function($orders) use ($master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission1;
                    $newCommission = 
                    intval(($order->totalFee - $order->expressFee) * $order->rate1 / 100);
                    $order->commission1 = $newCommission;
                    $order->commission1Time = now(); 
                    $order->save();

                    $type = $newCommission > $currentCommission ? 1 : 2;
                    $commission = $newCommission - $currentCommission;
                    $commission = $commission > 0 ? $commission : -$commission;

                    $this->addMoneyLog($order->oid, $type, $commission, $master, "第一次结算");
                }
            });
    }

    private function secondCommisson($master, $distributor_ids)
    {
        Order::query()
            ->with("product")
            ->where("itemId", $this->product->itemId)
            ->whereIn("distributorId", $distributor_ids)
            ->ofCommission()
            ->where("status", 70)
            ->where("commission2", "<>", 0)
            ->where("cpsPayStatus", 2)
            ->where("payTime", "<=", now()->subHours(admin_setting("commission2_days", 600)))
            ->chunk(1000, function($orders) use ($master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission2;
                    // $newCommission = 
                    //     ($order->totalFee - $order->expressFee) * $order->rate2 / 100;
                    $newCommission = $order->commissionTotal - $order->commission1;
                    $order->commission2 = $newCommission;
                    $order->commission2Time = now(); 
                    $order->save();

                    $type = $newCommission > $currentCommission ? 1 : 2;
                    $commission = $newCommission - $currentCommission;
                    $commission = $commission > 0 ? $commission : -$commission;

                    $this->addMoneyLog($order->oid, $type, $commission, $master, "第二次结算");
                }
            });
    }

    private function refund($master, $distributor_ids)
    {
        Order::query()
            ->with("product")
            ->where("itemId", $this->product->itemId)
            ->whereIn("distributorId", $distributor_ids)
            ->ofRefund()
            ->where("cpsPayStatus", 3)
            ->chunk(1000, function($orders) use ($master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission1 + $order->commission2;
                    if($currentCommission > 0) {
                        $newCommission1 = 
                        intval(($order->totalFee - $order->expressFee) * $order->rate1 / 100);
                        $newCommission2 = 
                        intval(($order->totalFee - $order->expressFee) * $order->rate2 / 100);
                        $order->commission1 = $order->commission1 > 0 ? $newCommission1 : 0;
                        $order->commission2 = $order->commission2 > 0 ? $newCommission2 : 0;
                        $order->save();

                        $commission = $order->commission1 + $order->commission2;

                        $commission = $commission - $currentCommission;
                        $commission = $commission > 0 ? $commission : -$commission;

                        $this->addMoneyLog($order->oid, 2, $commission, $master, "售后");
                    }
                }
            });
    }

    private function addMoneyLog($oid, $type, $commission, $master, $content)
    {
        $master = Master::find($master->id);
        $before_money = $master->commission_money;
        if($type == 1) {
            $master->increment("commission_money", $commission);
        } else {
            $master->decrement("commission_money", $commission);
        }
        $after_money = $master->commission_money;

        Money::query()
            ->create([
                "master_id" => $master->id,
                "oid" => $oid,
                "type" => $type,
                "money" => $commission,
                "before_money" => $before_money,
                "after_money" => $after_money,
                "content" => $content,
            ]);
    }
}
