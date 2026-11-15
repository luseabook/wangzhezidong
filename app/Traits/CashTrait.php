<?php

namespace App\Traits;

use App\Models\Cash;
use App\Models\Master;
use App\Models\Money;
use App\Models\Order;

/**
 * 提现结算的相关方法
 */
trait CashTrait
{
    /**
     * 第一次已结算
     *
     * @return float
     */
    public function firstCommission($distributor_ids)
    {
        return Order::query()
            ->whereIn('distributorId', $distributor_ids)
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2])
            ->sum('commission1') / 100;
    }

    /**
     * 第二次已结算
     *
     * @return float
     */
    public function secondCommission($distributor_ids)
    {
        return Order::query()
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2])
            ->whereIn('distributorId', $distributor_ids)
            ->sum('commission2') / 100;
    }

    /**
     * 可结算总金额
     *
     * @return float
     */
    public function commission($distributor_ids)
    {
        return Order::query()
            ->whereIn('distributorId', $distributor_ids)
            ->ofCommission()
            ->sum('commissionTotal') / 100;
    }

    /**
     * 预估总佣金
     *
     * @return float
     */
    public function allCommissionByModel($model)
    {
        return $model
            ->ofCommission()
            ->sum('commissionTotal') / 100;
    }

    /**
     * 已结算总佣金
     *
     * @return float
     */
    public function doneCommissionByModel($model)
    {
        $model = $model
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2]);

        return ($model->sum('commission1') + $model->sum('commission2')) / 100;
    }

    /**
     * 已结算总金额
     *
     * @return float
     */
    public function commissioned($distributor_ids)
    {
        $fisrtCommission = $this->firstCommission($distributor_ids);
        $secondCommission = $this->secondCommission($distributor_ids);

        return bcadd($fisrtCommission, $secondCommission, 2);
    }

    /**
     * 抽成总金额
     *
     * @return float
     */
    public function parentCommissionedByModel($model, $master_id)
    {
        $childDistributor_ids = [];
        $childMaster = Master::query()
            ->where('parent_id', $master_id)
            ->with(['distributors' => function ($query) {
                $query->where('status', 1);
            }])->get();
        foreach ($childMaster as $item) {
            $ids = $item->distributors->pluck('distributorId')->toArray();
            $childDistributor_ids = array_merge($childDistributor_ids, $ids);
        }

        $model = $model->whereIn('distributorId', $childDistributor_ids)
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2]);

        return bcadd((clone $model)->sum('parent_commission1') / 100, $model->sum('parent_commission2') / 100, 2);
    }

    /**
     * 抽成总金额
     *
     * @return float
     */
    public function parentCommissioned($distributor_ids)
    {
        $fisrtCommission = $this->parentFirstCommission($distributor_ids);
        $secondCommission = $this->parentSecondCommission($distributor_ids);

        return bcadd($fisrtCommission, $secondCommission, 2);
    }

    /**
     * 第一次已结算抽成
     *
     * @return float
     */
    public function parentFirstCommission($distributor_ids)
    {
        return Order::query()
            ->whereIn('distributorId', $distributor_ids)
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2])
            ->sum('parent_commission1') / 100;
    }

    /**
     * 第二次已结算抽成
     *
     * @return float
     */
    public function parentSecondCommission($distributor_ids)
    {
        return Order::query()
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2])
            ->whereIn('distributorId', $distributor_ids)
            ->sum('parent_commission2') / 100;
    }

    /**
     * 已占用提现总金额
     * 待审核和审核通过的提现都会占用可提现额度。
     *
     * @return float|string
     */
    public function cashed($master_id)
    {
        $total = Cash::query()
            ->where('master_id', $master_id)
            ->whereIn('status', [0, 1])
            ->sum('amount');

        return bcadd($total, 0, 2);
    }

    /**
     * 已提现总金额
     * 待审核和审核通过的，排除指定明细的提现
     *
     * @return float
     */
    public function cashedExcludeCashId($master_id, $cash_id)
    {
        $total = Cash::query()
            ->where('id', '<>', $cash_id)
            ->where('master_id', $master_id)
            ->whereIn('status', [0, 1])
            ->sum('amount');

        return bcadd($total, 0, 2);
    }

    /**
     * 预结算金额
     *
     * @return float
     */
    public function allCommission($master_id)
    {
        $master = $this->masterWithActiveDistributors($master_id);

        if (! $master) {
            return '0.00';
        }

        $ownCommission = $this->commission($this->activeDistributorIds($master));
        $childCommission = $this->parentCommissionByModel(
            Order::query()->whereIn(
                'distributorId',
                $this->activeChildDistributorIds($master->id)
            )
        );

        return bcadd($ownCommission, $childCommission, 2);
    }

    /**
     * 待结算金额
     *
     * @return float|string
     */
    public function unCommission($master_id)
    {
        $master = $this->masterWithActiveDistributors($master_id);

        if (! $master) {
            return '0.00';
        }

        $ownQuery = Order::query()
            ->whereIn('distributorId', $this->activeDistributorIds($master));
        $childQuery = Order::query()
            ->whereIn('distributorId', $this->activeChildDistributorIds($master->id));

        $allCommission = bcadd(
            $this->allCommissionByModel(clone $ownQuery),
            $this->parentCommissionByModel(clone $childQuery),
            2
        );
        $doneCommission = bcadd(
            $this->doneCommissionByModel(clone $ownQuery),
            $this->doneParentCommissionByModel(clone $childQuery),
            2
        );

        return bcsub($allCommission, $doneCommission, 2);
    }

    /**
     * Load a master together with only the distributors that are currently active.
     *
     * Keeping this scope in one place prevents dashboard metrics from silently
     * disagreeing about whether disabled distributor IDs should be included.
     */
    protected function masterWithActiveDistributors($master_id)
    {
        return Master::query()
            ->with(['distributors' => function ($query) {
                $query->where('status', 1);
            }])
            ->find($master_id);
    }

    /**
     * @return array
     */
    protected function activeDistributorIds(Master $master)
    {
        return $master->distributors
            ->pluck('distributorId')
            ->filter(function ($id) {
                return $id !== null && $id !== '';
            })
            ->values()
            ->toArray();
    }

    /**
     * Get active distributor IDs belonging to the master's direct children.
     * These orders store the master's earnings in parent_commission* fields.
     *
     * @param  int  $master_id
     * @return array
     */
    protected function activeChildDistributorIds($master_id)
    {
        return Master::query()
            ->where('parent_id', $master_id)
            ->with(['distributors' => function ($query) {
                $query->where('status', 1);
            }])
            ->get()
            ->flatMap(function ($child) {
                return $child->distributors->pluck('distributorId');
            })
            ->filter(function ($id) {
                return $id !== null && $id !== '';
            })
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Sum the total commission retained by a parent from child orders.
     *
     * @param  mixed  $model
     * @return float
     */
    protected function parentCommissionByModel($model)
    {
        return $model->ofCommission()->sum('parent_commission') / 100;
    }

    /**
     * Sum the settled commission retained by a parent from child orders.
     *
     * @param  mixed  $model
     * @return float
     */
    protected function doneParentCommissionByModel($model)
    {
        $model = $model
            ->ofCommission()
            ->whereIn('cpsPayStatus', [1, 2]);

        return ($model->sum('parent_commission1') + $model->sum('parent_commission2')) / 100;
    }

    /**
     * 可提现金额
     *
     * @return float
     */
    public function canCommission($master_id)
    {
        $master = $this->masterWithActiveDistributors($master_id);

        if (! $master) {
            return '0.00';
        }

        $commissioned = $this->commissioned($this->activeDistributorIds($master));
        $childCommissioned = $this->parentCommissioned($this->activeChildDistributorIds($master->id));
        $cashed = $this->cashed($master_id);

        // 团长抽成
        $leaderComissioned = $this->leaderCommissioned($master_id);

        return bcsub(
            bcadd(bcadd($childCommissioned, $commissioned, 2), $leaderComissioned, 2),
            $cashed,
            2
        );
    }

    /**
     * 团长结算金额
     */
    public function leaderCommissioned($master_id)
    {
        // 只统计团长分佣。上级抽成虽然也是 is_parent=1，但已经由
        // parentCommissioned() 按订单字段计算；这里再算一次会重复，
        // 而且历史上级流水金额与订单口径不同，会把上级可提现算错。
        $addMoney = Money::query()
            ->where('master_id', $master_id)
            ->where('is_parent', 1)
            ->where('is_leader', 1)
            ->where('type', 1)
            ->sum('money') / 100;

        $money = Money::query()
            ->where('master_id', $master_id)
            ->where('is_parent', 1)
            ->where('is_leader', 1)
            ->where('type', 2)
            ->sum('money') / 100;

        return bcsub($addMoney, $money, 2);
    }

    /**
     * 可提现金额
     * 排除指定的提现明细
     *
     * @return float
     */
    public function canCommissionExcludeCashId($master_id, $cash_id)
    {
        $master = $this->masterWithActiveDistributors($master_id);

        if (! $master) {
            return '0.00';
        }

        $commissioned = $this->commissioned($this->activeDistributorIds($master));
        $childCommissioned = $this->parentCommissioned($this->activeChildDistributorIds($master->id));
        $leaderComissioned = $this->leaderCommissioned($master_id);
        $cashed = Cash::query()
            ->where('id', '<>', $cash_id)
            ->where('master_id', $master_id)
            ->whereIn('status', [0, 1])
            ->sum('amount');

        return bcsub(
            bcadd(bcadd($childCommissioned, $commissioned, 2), $leaderComissioned, 2),
            $cashed,
            2
        );
    }

    /**
     * 售后金额
     */
    public function refundCommission($distributor_ids)
    {
        $model = Order::query()
            ->whereIn('distributorId', $distributor_ids)
            ->ofRefund();
        $commission1 = (clone $model)->sum('commission1') / 100;
        $commission2 = $model->sum('commission2') / 100;

        return bcadd($commission1, $commission2, 2);
    }

    /**
     * 售后金额
     */
    public function refundCommissionByModel($model)
    {
        $model = $model->ofRefund();
        $commission1 = (clone $model)->sum('commission1') / 100;
        $commission2 = $model->sum('commission2') / 100;

        return bcadd($commission1, $commission2, 2);
    }
}
