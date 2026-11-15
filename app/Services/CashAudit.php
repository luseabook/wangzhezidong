<?php

namespace App\Services;

use App\Models\Cash;
use App\Models\Master;
use App\Traits\CashTrait;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 提现批量审核。
 *
 * 确认（status=1）前按当前可提现额按达人分组复核：
 * 本次选择里待审核的占用先加回来，再与本次要确认的合计比较；
 * 任何一组不足则整批不执行，返回失败明细，避免超额打款。
 */
class CashAudit
{
    use CashTrait;

    /**
     * 批量确认。
     *
     * @return array{ok:bool, approved:array, skipped:array, failures:array}
     */
    public function approve(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return ['ok' => false, 'approved' => [], 'skipped' => [], 'failures' => []];
        }

        return DB::transaction(function () use ($ids) {
            $cashes = Cash::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cashes->isEmpty()) {
                return ['ok' => false, 'approved' => [], 'skipped' => [], 'failures' => []];
            }

            $masterIds = $cashes->pluck('master_id')->unique()->sort()->values()->all();
            Master::query()
                ->whereIn('id', $masterIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $failures = [];
            $toApprove = [];

            foreach ($cashes->groupBy('master_id') as $masterId => $group) {
                $approveIds = [];
                $requested = '0.00';

                foreach ($group as $cash) {
                    if ((int) $cash->status === 1) {
                        continue;
                    }
                    $approveIds[] = $cash->id;
                    $requested = bcadd($requested, (string) $cash->amount, 2);
                }

                if (empty($approveIds)) {
                    continue;
                }

                // canCommission() 已经扣掉了所有待审核/已确认的占用；
                // 把本次选择里待审核(status=0)的占用先加回来，才是这批最多能确认的金额。
                $selectedPending = '0.00';
                foreach ($group as $cash) {
                    if ((int) $cash->status === 0) {
                        $selectedPending = bcadd($selectedPending, (string) $cash->amount, 2);
                    }
                }

                $available = bcadd(
                    (string) $this->canCommission((int) $masterId),
                    $selectedPending,
                    2
                );

                if (bccomp($requested, $available, 2) > 0) {
                    $failures[] = [
                        'master_id' => (int) $masterId,
                        'available' => $available,
                        'requested' => $requested,
                        'ids' => $approveIds,
                    ];
                    continue;
                }

                $toApprove = array_merge($toApprove, $approveIds);
            }

            $skipped = $cashes
                ->filter(fn ($cash) => (int) $cash->status === 1)
                ->pluck('id')
                ->all();

            if (! empty($failures)) {
                return [
                    'ok' => false,
                    'approved' => [],
                    'skipped' => $skipped,
                    'failures' => $failures,
                ];
            }

            if (! empty($toApprove)) {
                Cash::query()
                    ->whereIn('id', $toApprove)
                    ->where('status', '<>', 1)
                    ->update([
                        'status' => 1,
                        'pay_at' => now(),
                        'remark' => null,
                    ]);
            }

            $this->syncCashMoney($masterIds);

            return [
                'ok' => true,
                'approved' => $toApprove,
                'skipped' => $skipped,
                'failures' => [],
            ];
        });
    }

    /**
     * 批量设置状态（驳回、退回待审核等）。已确认的记录不参与。
     */
    public function setStatus(array $ids, int $status, ?string $remark = null): array
    {
        if ($status === 1) {
            throw new InvalidArgumentException('确认提现请使用 approve()，不要走 setStatus()');
        }

        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return ['updated' => [], 'skipped' => []];
        }

        return DB::transaction(function () use ($ids, $status, $remark) {
            $cashes = Cash::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $masterIds = $cashes->pluck('master_id')->unique()->sort()->values()->all();
            Master::query()
                ->whereIn('id', $masterIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $updateIds = $cashes
                ->filter(fn ($cash) => (int) $cash->status !== 1)
                ->pluck('id')
                ->all();

            if (! empty($updateIds)) {
                Cash::query()
                    ->whereIn('id', $updateIds)
                    ->where('status', '<>', 1)
                    ->update([
                        'status' => $status,
                        'remark' => $remark,
                        'pay_at' => null,
                    ]);
            }

            $this->syncCashMoney($masterIds);

            return [
                'updated' => $updateIds,
                'skipped' => $cashes
                    ->filter(fn ($cash) => (int) $cash->status === 1)
                    ->pluck('id')
                    ->all(),
            ];
        });
    }

    private function syncCashMoney(array $masterIds): void
    {
        foreach (array_unique($masterIds) as $masterId) {
            $cashed = Cash::query()
                ->where('master_id', $masterId)
                ->where('status', 1)
                ->sum('amount');

            Master::query()
                ->where('id', $masterId)
                ->update(['cash_money' => $cashed]);
        }
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}