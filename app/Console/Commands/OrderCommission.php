<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use App\Services\OrderSettlementReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

        // 防止定时任务、商品改率 Job 和重复排队同时跑出重复流水。
        $lock = Cache::lock('create:order-commission', 3600);
        if (! $lock->get()) {
            $this->info('已有结算任务在运行，本轮跳过');
            return;
        }

        try {
            $this->refundToNotRefund();
            $this->closedToNormal();
            $this->recalcDirtyOrders();

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
                    $this->close($master, $distributor_ids);
                });
        } finally {
            $lock->release();
        }
    }

    /**
     * 售后订单转为未售后
     */
    private function refundToNotRefund()
    {
        $this->info("开始售后订单转为未售后");
        $total = 0;
        $resetter = app(OrderSettlementReset::class);

        Order::query()
            ->ofNotRefund()
            ->where("cpsPayStatus", 3)
            ->chunkById(1000, function($orders) use (&$total, $resetter) {
                foreach ($orders as $order) {
                    $resetter->reset($order);
                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束售后订单转为未售后");
    }

    /**
     * 已关闭（status 0/80）但后来又恢复到可结算状态的订单：
     * 清空关闭时的结算状态，让主流程按当前数据重新结算。
     */
    private function closedToNormal()
    {
        $this->info("开始恢复已关闭但重新有效的订单");
        $total = 0;
        $resetter = app(OrderSettlementReset::class);

        Order::query()
            ->where("cpsPayStatus", 4)
            ->whereIn("status", [30, 40, 50, 70])
            ->whereIn("refundStatus", [0, 70])
            ->chunkById(1000, function($orders) use (&$total, $resetter) {
                foreach ($orders as $order) {
                    $resetter->reset($order);
                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束恢复已关闭订单");
    }

    /**
     * 订单金额/商品/归属变更后，旧佣金不能沿用：
     * 先按账本实际入账冲回，再重置结算字段，后面的 commisson/first/second 会重算。
     */
    private function recalcDirtyOrders()
    {
        $this->info("开始处理结算输入变更的订单");
        $total = 0;
        $resetter = app(OrderSettlementReset::class);

        Order::query()
            ->where("settlement_dirty", 1)
            ->chunkById(500, function($orders) use (&$total, $resetter) {
                foreach ($orders as $order) {
                    if (in_array((int) $order->cpsPayStatus, [3, 4], true)) {
                        // 已售后/已关闭的订单由恢复流程按当前字段处理。
                        $order->settlement_dirty = 0;
                        $order->save();
                        continue;
                    }

                    if (! in_array((int) $order->refundStatus, [0, 70], true)) {
                        if ((int) $order->cpsPayStatus === 0) {
                            // 未结算的售后订单没有扣回可做；清掉旧金额，退款恢复后按当前值重算。
                            $resetter->reverseMoney($order, "订单变更冲正");
                            $resetter->reset($order);
                            $total += 1;
                        }
                        // cpsPayStatus 1/2 的售后订单留给 refund() 按原结算金额扣回；
                        // 保留 settlement_dirty，下一轮确认转 3 后再清。
                        continue;
                    }

                    $resetter->reverseMoney($order, "订单变更冲正");
                    $resetter->reset($order);
                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info("结束处理结算输入变更的订单");
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
                        // 上级抽成是订单金额的百分点，不能超过该订单的达人净分佣率。
                        $effectiveParentRate = min((float) $master->parent_rate, max(0, (float) $order->rate));
                        $newCommission = intval(($order->totalFee - $order->expressFee) * ($order->rate - $effectiveParentRate) / 100);
                        $parent_commission = intval(($order->totalFee - $order->expressFee) * $effectiveParentRate / 100);
                        $commission_rate = intval($order->rate - $effectiveParentRate);
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
            // 结算闸门看 commission1/2 与 cpsPayStatus，不再依赖 importStatus。
            ->where("commission1", 0)
            ->where("commission2", 0)
            // 结算等待期以发货时间为准，与后台「结算设置」文案一致。
            ->where("sendTime", "<=", now()->subHours(admin_setting("commission1_days", 72)))
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $currentCommission = $order->commission1;

                    // 是否有上级和分佣百分比
                    if($master->parent_id && $master->parent_rate) {
                        // 上级首笔按订单渠道的结算比例拆分（视频号用 wechat_rate*），
                        // 与 Order::rate1 的拆分口径保持一致；parent_rate 不能超过订单净分佣率。
                        $effectiveParentRate = min((float) $master->parent_rate, max(0, (float) $order->rate));
                        $parent_rate = $effectiveParentRate * $order->stage1_percent / 100;

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

                    $order->importStatus = 1;
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
                        // 把上级账本调整到订单 parent_commission1。
                        // 旧实现用 commission/(1-parent_rate/100)-commission 推算，与订单字段口径不同，
                        // 会把上级抽成记少；退款时又按订单字段全额扣回，导致上级余额变负。
                        $this->syncParentMoneyLog($order->oid, $parent_commission1, $master, "第一次结算", $order->rate, $order->rate1, $order->rate2);
                    }

                    // 团长分佣记录
                    if($order->leader_rate1 > 0) {
                        if($order->leader_master_id == 0) {
                            $order->leader_master_id = $order->product->master_id;
                            $order->save();
                        }
                        $leaderCommission =
                        intval(($order->totalFee - $order->expressFee) * $order->leader_rate1 / 100);
                        $this->syncLeaderMoneyLog($order->oid, $leaderCommission, $order->product->master_id, "第一次结算", $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2);
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
            // 不依赖 importStatus；commission2/commission1 已经能保证不重复结算。
            ->where("commission2", 0)
            // 第二笔同样按发货时间 + 配置小时数，且订单成功。
            ->where("sendTime", "<=", now()->subHours(admin_setting("commission2_days", 600)))
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

                    $order->importStatus = 2;
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
                        // 把上级账本调整到 parent_commission1 + parent_commission2。
                        $this->syncParentMoneyLog($order->oid, $order->parent_commission1 + $parent_commission2, $master, "第二次结算", $order->rate, $order->rate1, $order->rate2);
                    }

                    // 团长分佣记录
                    if($order->leader_rate2 > 0) {
                        if($order->leader_master_id == 0) {
                            $order->leader_master_id = $order->product->master_id;
                            $order->save();
                        }

                        $leaderFirst = intval(($order->totalFee - $order->expressFee) * $order->leader_rate1 / 100);
                        $leaderSecond = intval(($order->totalFee - $order->expressFee) * $order->leader_rate2 / 100);
                        // 目标金额是两笔累计，syncLeaderMoneyLog 会补上首笔后的差额。
                        $this->syncLeaderMoneyLog($order->oid, $leaderFirst + $leaderSecond, $order->product->master_id, "第二次结算", $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2);
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
            // 售后扣回不依赖导入批次：只要订单已结算且当前处于售后就冲回。
            // 处理后 cpsPayStatus=3，不会重复命中 [1,2]。
            ->whereIn("cpsPayStatus", [1, 2])
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $this->reverseOrderCommission($order, $master, "售后");
                    // 售后保留当前结算阶段（1 或 2），用 cpsPayStatus=3 表示售后。
                    $order->cpsPayStatus = 3;
                    $order->save();
                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束售后");
    }

    /**
     * 订单关闭（status 0/80）扣回：只处理已经结算过（cpsPayStatus 1/2）的订单；
     * 未结算的订单没有佣金可扣，不动。
     */
    private function close($master, $distributor_ids)
    {
        $this->info($master->username . " 开始订单关闭扣回");
        $total = 0;
        Order::query()
            ->with("product")
            ->whereIn("distributorId", $distributor_ids)
            ->whereIn("status", [0, 80])
            ->ofNotRefund()
            ->whereIn("cpsPayStatus", [1, 2])
            ->chunkById(1000, function($orders) use (&$total, $master) {
                foreach ($orders as $order) {
                    $this->reverseOrderCommission($order, $master, "订单关闭");
                    $order->cpsPayStatus = 4;
                    $order->save();
                    $total += 1;
                }
            });

        $this->info("共处理 {$total} 笔订单");
        $this->info($master->username . " 结束订单关闭扣回");
    }

    /**
     * 按实际账本净额冲回该订单的达人/上级/团长佣金。
     * 售后与订单关闭共用，调用方负责设置 cpsPayStatus。
     */
    private function reverseOrderCommission($order, $master, $content)
    {
        $commission = $order->commission1 + $order->commission2;
        $this->addMoneyLog($order->oid, 2, $commission, $master, $content, $order->rate, $order->rate1, $order->rate2);

        if($master->parent_id && $master->parent_rate) {
            // 只冲回该订单实际记到上级账本的净额。
            // 历史版本的上级流水金额可能小于订单 parent_commission*，
            // 若按订单字段全额扣回会把上级 commission_money 扣成负数。
            $parentBooked = $this->parentBookedAmount($order->oid, $master->parent_id);

            if($parentBooked > 0) {
                $this->addMoneyLog($order->oid, 2, $parentBooked, $master->parent, $content, $order->rate, $order->rate1, $order->rate2, 1, $master->parent_rate);
            }
        }

        if($order->leader_rate > 0) {
            if($order->leader_master_id == 0) {
                $order->leader_master_id = $order->product->master_id;
                $order->save();
            }

            // 只冲回该订单实际记到团长账本的净额（首笔后扣回不会多扣尾笔）。
            $this->syncLeaderMoneyLog($order->oid, 0, $order->product->master_id, $content, $order->product->itemId, $order->leader_rate, $order->leader_rate1, $order->leader_rate2, true);
        }
    }

    /**
     * 某订单已记入指定上级账本的净额（分）。
     * 只统计上级抽成，不含团长分佣（团长流水 is_leader=1）。
     */
    private function parentBookedAmount($oid, $parentMasterId)
    {
        $net = Money::query()
            ->where("master_id", $parentMasterId)
            ->where("oid", $oid)
            ->where("is_parent", 1)
            ->where("is_leader", 0)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 1 THEN money ELSE -money END), 0) as net")
            ->value("net");

        return intval($net);
    }

    /**
     * 把上级账本调整到目标金额，差额记一条流水。
     * 目标金额来自订单的 parent_commission* 字段，保证账本与订单口径一致，
     * 也避免重跑时按旧差额重复记账。
     */
    private function syncParentMoneyLog($oid, $target, $master, $content, $rate, $rate1, $rate2)
    {
        if (! $master || ! $master->parent_id || ! $master->parent) {
            return;
        }

        $booked = $this->parentBookedAmount($oid, $master->parent_id);
        $delta = intval($target) - $booked;

        if ($delta === 0) {
            return;
        }

        $type = $delta > 0 ? 1 : 2;
        $this->addMoneyLog($oid, $type, abs($delta), $master->parent, $content, $rate, $rate1, $rate2, 1, $master->parent_rate);
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

    /**
     * 某订单已记入指定团长账本的净额（分）。
     * 统计该订单/团长/商品的团长流水，含售后冲减。
     */
    private function leaderBookedAmount($oid, $masterId, $itemId)
    {
        $net = Money::query()
            ->where("master_id", $masterId)
            ->where("oid", $oid)
            ->where("is_parent", 1)
            ->where("is_leader", 1)
            ->where("itemId", $itemId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 1 THEN money ELSE -money END), 0) as net")
            ->value("net");

        return intval($net);
    }

    /**
     * 把团长账本调整到目标金额，差额记一条流水。
     * $reverseOnly=true 时只冲回已入账的正净额（售后退款用），不会反向补记。
     */
    private function syncLeaderMoneyLog($oid, $target, $master_id, $content, $itemId, $rate, $rate1=0, $rate2=0, $reverseOnly=false)
    {
        $booked = $this->leaderBookedAmount($oid, $master_id, $itemId);

        if ($reverseOnly) {
            if ($booked <= 0) {
                return;
            }
            $delta = -$booked;
        } else {
            $delta = intval($target) - $booked;
            if ($delta === 0) {
                return;
            }
        }

        $commission = abs($delta);
        $type = $delta > 0 ? 1 : 2;

        $master = Master::find($master_id);
        if (! $master) {
            return;
        }

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
                "is_leader" => 1,
                "itemId" => $itemId,
            ]);
    }
}
