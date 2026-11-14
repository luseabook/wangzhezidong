<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// 订单分佣处理

// 订单导入状态
// 0 待处理 1完成
// 结算状态
// 0 待结算 1第一次已结算 2第二次已结算 3售后

// 定时任务 订单结算
// 0 0 -> 1
// 0 1 -> 2 1
// 0 -> 3 1

class OrderCommission extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:order-commission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单分佣处理';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', "1G");

        $this->refundToNotRefund();

        Master::query()
            ->with("parent")
            ->get()
            ->each(function ($master) {
                $distributor_ids = Distributor::query()
                    ->where("master_id", $master->id)
                    ->where("status", 1)
                    ->get()
                    ->pluck("distributorId")
                    ->toArray();

                $this->commisson($master, $distributor_ids);
                $this->firstCommisson($master, $distributor_ids);
                $this->secondCommisson($master, $distributor_ids);
                $this->refund($master, $distributor_ids);
            });
    }

    /**
     * 售后订单转为未售后
     */
    private function refundToNotRefund()
    {
        $this->info("开始售后订单转为未售后");
        $total = 0;
        Order::query()
            ->ofNotRefund()
            ->where("cpsPayStatus", 3)
            ->chunkById(1000, function($orders) use (&$total) {
                foreach ($orders as $order) {
                    $order->cpsPayStatus = 0;
                    $order->commissionTotal = 0;
                    $order->commission1 = 0;
                    $order->commission2 = 0;
                    $order->commission_rate = 0;
                    $order->commission1_rate = 0;
                    $order->commission2_rate = 0;
                    $order->save();

                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束售后订单转为未售后");
    }

    private function commisson($master, $distributor_ids)
    {
        $this->info($master->username . " 开始计算预估总佣金");
        $total = 0;
        Order::query()
            ->with("product")
            ->whereIn("distributorId", $distributor_ids)
            ->ofCommission()
            ->where("commissionTotal", 0)
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    // 是否有上级和分佣百分比
                    if($master->parent_id && $master->parent_rate) {
                        $newCommission = intval(($order->totalFee - $order->expressFee) * ($order->rate - $master->parent_rate) / 100);
                        $parent_commission = intval(($order->totalFee - $order->expressFee) * $master->parent_rate / 100);
                        $commission_rate = $order->rate - $master->parent_rate;
                    } else {
                        $newCommission = intval(($order->totalFee - $order->expressFee) * $order->rate / 100);
                        $parent_commission = 0;
                        $commission_rate = $order->rate;
                    }

                    $order->commissionTotal = $newCommission;
                    $order->parent_commission = $parent_commission;
                    $order->commission_rate = $commission_rate;
                    $order->save();

                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束计算预估总佣金");
    }

    private function firstCommisson($master, $distributor_ids)
    {
        $this->info($master->username . " 开始第一次分佣结算");
        $total = 0;
        Order::query()
            ->with("product")
            ->whereIn("distributorId", $distributor_ids)
            ->ofCommission()
            ->where("importStatus", 0)
            ->where("commission1", 0)
            ->where("commission2", 0)
            ->where("payTime", "<=", now()->subHours(admin_setting("commission1_days", 72)))
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission1;

                    // 是否有上级和分佣百分比
                    if($master->parent_id && $master->parent_rate) {
                        if($master->rate1 + $master->rate2)
                        {
                            $parent_rate =  $master->parent_rate * $master->rate1 / 100;
                        } else {
                            $parent_rate =  $master->parent_rate * admin_setting('commission1_rate', 50) / 100;
                        }

                        $newCommission = 
                        intval(($order->totalFee - $order->expressFee) * ($order->rate1 - $parent_rate) / 100);
                        $parent_commission1 = intval(($order->totalFee - $order->expressFee) * $parent_rate / 100);
                        $commission_rate = intval($order->rate1 - $parent_rate);
                    } else {
                        $newCommission = 
                        intval(($order->totalFee - $order->expressFee) * $order->rate1 / 100);
                        $parent_commission1 = 0;
                        $commission_rate = $order->rate1;
                    }

                    $order->cpsPayStatus = 1;
                    $order->commission1 = $newCommission;
                    $order->commission1_rate = $commission_rate;
                    $order->parent_commission1 = $parent_commission1;
                    $order->commission1Time = now();
                    $order->save();

                    $type = $newCommission > $currentCommission ? 1 : 2;
                    $commission = $newCommission - $currentCommission;
                    $commission = $commission > 0 ? $commission : -$commission;

                    $total += 1;

                    $this->addMoneyLog($order->oid, $type, $commission, $master, "第一次结算", $order->rate, $order->rate1, $order->rate2);
                    
                    if($master->parent_id && $master->parent_rate) {
                        $parent_commission = $commission / (1 - $master->parent_rate / 100) - $commission;
                        $this->addMoneyLog($order->oid, $type, $parent_commission, $master->parent, "第一次结算", $order->rate, $order->rate1, $order->rate2, 1, $master->parent_rate);
                    }

                    // 团长分佣记录
                    if($order->leader_rate1 > 0) {
                        if($order->leader_master_id == 0) {
                            $order->leader_master_id = $order->product->master_id;
                            $order->save();
                        }
                        $leaderCommission = 
                        intval(($order->totalFee - $order->expressFee) * $order->leader_rate1 / 100);
                        $this->addLeaderMoneyLog($order->oid, $leaderCommission, $order->product->master_id, "第一次结算", $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2);
                    }
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束第一次分佣结算");
    }

    private function secondCommisson($master, $distributor_ids)
    {
        $this->info($master->username . " 开始第二次分佣结算");
        $total = 0;
        Order::query()
            ->with("product")
            ->whereIn("distributorId", $distributor_ids)
            ->ofCommission()
            ->where("status", 70)
            ->where("importStatus", 0)
            ->where("commission2", 0)
            ->where("payTime", "<=", now()->subHours(admin_setting("commission2_days", 600)))
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission2;
                    // $newCommission = 
                    // intval(($order->totalFee - $order->expressFee) * $order->rate2 / 100);
                    // $newCommission = intval($order->commissionTotal - $order->commission1);
                    $newCommission = intval($order->commissionTotal - $order->commission1);

                    // 是否有上级和分佣百分比
                    if($master->parent_id && $master->parent_rate) {
                        $parent_commission2 = $order->parent_commission - $order->parent_commission1;
                        $commission_rate = $order->rate - $order->rate1;
                    } else {
                        $parent_commission2 = 0;
                        $commission_rate = $order->rate - $order->rate1;
                    }

                    $order->importStatus = 1;
                    $order->cpsPayStatus = 2;
                    $order->commission2 = $newCommission;
                    $order->commission2_rate = $commission_rate;
                    $order->parent_commission2 = $parent_commission2;
                    $order->commission2Time = now();
                    $order->save();

                    $type = $newCommission > $currentCommission ? 1 : 2;
                    $commission = $newCommission - $currentCommission;
                    $commission = $commission > 0 ? $commission : -$commission;

                    $total += 1;

                    $this->addMoneyLog($order->oid, $type, $commission, $master, "第二次结算", $order->rate, $order->rate1, $order->rate2);
                
                    if($master->parent_id && $master->parent_rate) {
                        $parent_commission = $commission / (1 - $master->parent_rate / 100) - $commission;
                        $this->addMoneyLog($order->oid, $type, $parent_commission, $master->parent, "第二次结算", $order->rate, $order->rate1, $order->rate2, 1, $master->parent_rate);
                    }

                    // 团长分佣记录
                    if($order->leader_rate2 > 0) {
                        if($order->leader_master_id == 0) {
                            $order->leader_master_id = $order->product->master_id;
                            $order->save();
                        }

                        $leaderCommission = 
                        intval(($order->totalFee - $order->expressFee) * $order->leader_rate2 / 100);
                        $this->addLeaderMoneyLog($order->oid, $leaderCommission, $order->product->master_id, "第二次结算", $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2);
                    }
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束第二次分佣结算");
    }

    private function refund($master, $distributor_ids)
    {
        $this->info($master->username . " 开始售后");
        $total = 0;
        Order::query()
            ->with("product")
            ->whereIn("distributorId", $distributor_ids)
            ->ofRefund()
            ->where("importStatus", 0)
            ->whereIn("cpsPayStatus", [1, 2])
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $commission = $order->commission1 + $order->commission2;
                    $order->importStatus = 1;
                    $order->cpsPayStatus = 3;
                    $order->save();

                    $total += 1;

                    $this->addMoneyLog($order->oid, 2, $commission, $master, "售后", $order->rate, $order->rate1, $order->rate2);
                
                    if($master->parent_id && $master->parent_rate) {
                        $parent_commission = $order->parent_commission1 + $order->parent_commission2;;
                        $this->addMoneyLog($order->oid, 2, $parent_commission, $master->parent, "售后", $order->rate, $order->rate1, $order->rate2, 1, $master->parent_rate);
                    }

                    // 团长分佣记录
                    if($order->leader_rate > 0) {
                        if($order->leader_master_id == 0) {
                            $order->leader_master_id = $order->product->master_id;
                            $order->save();
                        }
                        
                        $leaderCommission = 
                        intval(($order->totalFee - $order->expressFee) * $order->leader_rate / 100);
                        $this->addLeaderMoneyLog($order->oid, $leaderCommission, $order->product->master_id, "售后", $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2);
                    }
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束售后");
    }

    private function addMoneyLog($oid, $type, $commission, $master, $content, $rate, $rate1, $rate2, $is_parent=0, $parent_rate=0)
    {
        if($commission > 0) {
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
                    "rate" => $rate,
                    "rate1" => $rate1,
                    "rate2" => $rate2,
                    "is_parent" => $is_parent,
                    "parent_rate" => $parent_rate,
                ]);
        }
    }

    private function addLeaderMoneyLog($oid, $commission, $master_id, $content, $itemId, $rate, $rate1=0, $rate2=0)
    {
        if($commission > 0) {
            $type = $content == "售后" ? 2 : 1;
            $money = Money::query()
                ->where("master_id", $master_id)
                ->where("oid", $oid)
                ->where("type", $type)
                ->where("is_parent", 1)
                ->where("content", $content)
                ->where("itemId", $itemId)
                ->first();
            if($money) {
                $newCommission = $commission - $money->money;
                if($newCommission > 0) {
                    $commission = $newCommission;
                    $type = $type == 1 ? 1 : 2;
                } else {
                    $commission = -$newCommission;
                    $type = $type == 1 ? 2 : 1;
                }
            }

            $master = Master::find($master_id);
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
                    "rate" => $rate,
                    "rate1" => $rate1,
                    "rate2" => $rate2,
                    "is_parent" => 1,
                    "itemId" => $itemId,
                ]);
        }
    }
}
