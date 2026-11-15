<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use Dcat\Admin\Admin;
use Illuminate\Support\Carbon;

/**
 * 订单变更审计：把「哪个字段从什么变成什么」记下来。
 *
 * 只在值真正变化时写库，所以自动同步每轮重复 upsert 同一批订单不会产生噪音。
 * 写入用批量 insert，避免 1000+ 单时逐条 save 拖慢导入。
 */
class OrderObserver
{
    /** 只记业务字段；佣金计算过程中的中间字段（commissionTotal / importStatus / cpsPayStatus 等）不记 */
    public const TRACKED = [
        'status',
        'refundStatus',
        'sendTime',
        'payTime',
        'totalFee',
        'expressFee',
        'discountFee',
        'commissionRate',
        'estimatedIncome',
        'distributorId',
        'distributorName',
    ];

    /**
     * 影响结算金额/归属的字段。
     * 这些字段变化时，已计算的佣金可能失效，标记 settlement_dirty 等待主命令重算。
     */
    public const SETTLEMENT_INPUTS = [
        'totalFee',
        'expressFee',
        'itemId',
        'distributorId',
        'sellerType',
    ];

    /** 自动导入时由 DealImportOrder 之外的调用方设置（不改原 Job，通过中间件/闭包设置） */
    public static ?int $orderLogId = null;

    public function created(Order $order): void
    {
        $this->safeInsert([
            $this->row($order, '__created', null, '创建'),
        ]);
    }

    public function updating(Order $order): void
    {
        $dirty = $order->getDirty();
        if (empty($dirty)) {
            return;
        }

        $this->markSettlementDirty($order, $dirty);

        $rows = [];
        foreach (self::TRACKED as $field) {
            if (!array_key_exists($field, $dirty)) {
                continue;
            }
            $old = $order->getOriginal($field);
            $new = $dirty[$field];
            if ($this->same($old, $new)) {
                continue;
            }
            $rows[] = $this->row($order, $field, $old, $new);
        }

        if (!empty($rows)) {
            $this->safeInsert($rows);
        }
    }

    /**
     * 标记需要重算的订单。
     * 只标记，不在导入/编辑过程中做资金冲正；冲正与重结由结算命令统一处理。
     */
    private function markSettlementDirty(Order $order, array $dirty): void
    {
        if (! array_intersect(array_keys($dirty), self::SETTLEMENT_INPUTS)) {
            return;
        }

        // 已售后/已关闭的订单不在这里标记；恢复时由对应流程按当前字段重结。
        if (in_array((int) $order->cpsPayStatus, [3, 4], true)) {
            return;
        }

        // 还没算过佣金的订单不需要标记：主命令下一次会按当前字段直接计算。
        if ((int) $order->commissionTotal === 0
            && (int) $order->commission1 === 0
            && (int) $order->commission2 === 0) {
            return;
        }

        $order->settlement_dirty = 1;
    }

    /**
     * 审计写入绝不允许影响订单本身的保存：
     * 未跑迁移、表缺失、字段超长等情况下一律吞掉并记日志。
     */
    private function safeInsert(array $rows): void
    {
        try {
            OrderHistory::query()->insert($rows);
        } catch (\Throwable $e) {
            try {
                logger()->warning('[order-history] 写入失败，已忽略', [
                    'rows' => count($rows),
                    'err'  => $e->getMessage(),
                ]);
            } catch (\Throwable $ignore) {
                // 日志也不可用时彻底静默
            }
        }
    }

    private function row(Order $order, string $field, $old, $new): array
    {
        $source = $this->source();

        return [
            'oid'          => $order->oid,
            'order_id'     => $order->id,
            'field'        => $field,
            'old_value'    => $this->str($old),
            'new_value'    => $this->str($new),
            'source'       => $source,
            // 批次只归属「自动导入」产生的变更；手工/系统任务留空，避免误导
            'order_log_id' => $source === 1 ? (self::$orderLogId ?? $order->log_id) : null,
            'operator'     => $this->operator(),
            'changed_at'   => now(),
        ];
    }

    private function source(): int
    {
        if (self::$orderLogId !== null) {
            return 1;                       // 自动导入
        }
        return app()->runningInConsole() ? 2 : 0;   // 系统任务 / 手工
    }

    private function operator(): ?string
    {
        try {
            if (!app()->runningInConsole() && Admin::user()) {
                return mb_substr((string) Admin::user()->username, 0, 64);
            }
        } catch (\Throwable $e) {
            // 后台未登录（如队列内）时忽略
        }
        return null;
    }

    private function same($old, $new): bool
    {
        if ($old instanceof Carbon || $new instanceof Carbon) {
            $o = $old ? Carbon::parse($old)->timestamp : null;
            $n = $new ? Carbon::parse($new)->timestamp : null;
            return $o === $n;
        }
        if ($old === null && ($new === '' || $new === null)) {
            return true;
        }
        if (is_numeric($old) && is_numeric($new)) {
            return (string) $old === (string) $new;
        }
        return (string) $old === (string) $new;
    }

    private function str($v): ?string
    {
        if ($v === null) {
            return null;
        }
        if ($v instanceof Carbon) {
            $v = $v->toDateTimeString();
        }
        $s = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        return mb_strlen($s) > 500 ? mb_substr($s, 0, 500) . '…' : $s;
    }
}