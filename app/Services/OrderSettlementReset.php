<?php

namespace App\Services;

use App\Models\Master;
use App\Models\Money;
use App\Models\Order;

/**
 * 订单结算状态冲正与重置。
 *
 * 商品改率、订单金额/商品/归属变更、售后恢复等场景共用同一套实现，
 * 避免多处复制「首笔/尾笔/上级/团长」的冲正逻辑导致口径不一致。
 */
class OrderSettlementReset
{
    /**
     * 按订单在 money 里的实际入账分组冲回（含上级、团长流水）。
     * 只冲回正净额；已冲平的组不动。
     */
    public function reverseMoney(Order $order, string $content = '结算重算冲正'): void
    {
        $rows = Money::query()
            ->where('oid', $order->oid)
            ->selectRaw('master_id, is_parent, is_leader, itemId, SUM(CASE WHEN type = 1 THEN money ELSE -money END) as net')
            ->groupBy('master_id', 'is_parent', 'is_leader', 'itemId')
            ->get();

        foreach ($rows as $row) {
            $net = intval($row->net);
            if ($net <= 0) {
                continue;
            }

            $master = Master::find($row->master_id);
            if (! $master) {
                continue;
            }

            $before_money = $master->commission_money;
            $master->decrement('commission_money', $net);
            $after_money = $master->commission_money;

            Money::query()->create([
                'master_id' => $master->id,
                'oid' => $order->oid,
                'type' => 2,
                'money' => $net,
                'before_money' => $before_money,
                'after_money' => $after_money,
                'content' => $content,
                'rate' => $order->commission_rate,
                'rate1' => $order->commission1_rate,
                'rate2' => $order->commission2_rate,
                'is_parent' => (int) $row->is_parent,
                'is_leader' => (int) $row->is_leader,
                'itemId' => (int) $row->itemId,
            ]);
        }
    }

    /**
     * 清空订单的结算状态，让主命令按当前数据从头计算。
     * 保留 status / refundStatus / 支付与发货时间等业务字段。
     */
    public function reset(Order $order): void
    {
        $order->commissionTotal = 0;
        $order->commission_rate = 0;
        $order->commission1 = 0;
        $order->commission1_rate = 0;
        $order->commission1Time = null;
        $order->commission2 = 0;
        $order->commission2_rate = 0;
        $order->commission2Time = null;
        $order->parent_commission = 0;
        $order->parent_commission1 = 0;
        $order->parent_commission2 = 0;
        $order->cpsPayStatus = 0;
        $order->importStatus = 0;
        $order->leader_master_id = 0;
        $order->settlement_dirty = 0;
        $order->save();
    }
}