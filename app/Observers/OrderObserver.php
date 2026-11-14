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