<?php

namespace App\Jobs;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use App\Traits\CashTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateMasterCommission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CashTrait;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Master::query()
            ->get()
            ->each(function ($master) {
                $distributor_ids = Distributor::query()
                    ->where("master_id", $master->id)
                    ->where("status", 1)
                    ->get()
                    ->pluck("distributorId")
                    ->toArray();

                    $model = Order::query()
                        ->whereIn("distributorId", $distributor_ids);

                    $commissioned = $this->doneCommissionByModel((clone $model));
                    $cashed = $this->cashed($master->id);
                    $canCommission = bcsub($commissioned, $cashed, 2);

                    $childDistributor_ids = [];
                    $childMaster = Master::query()
                        ->where("parent_id", $master->id)
                        ->with(["distributors" => function($query) {
                            $query->where("status", 1);
                        }])->get();
                    foreach ($childMaster as $item) {
                        $ids = $item->distributors->pluck("distributorId")->toArray();
                        $childDistributor_ids = array_merge($childDistributor_ids, $ids);
                    }
                    $childCommission = $this->parentCommissioned($childDistributor_ids);

                    // 与 CashTrait::canCommission() 保持同一口径：
                    // 可提现 = 已结算 + 下级抽成 + 团长流水 - 已占用提现。
                    $leaderCommission = $this->leaderCommissioned($master->id);

                    $master->commissioned = $commissioned;
                    $master->cashed = $cashed;
                    $master->canCommission = bcadd(bcadd($canCommission, $childCommission, 2), $leaderCommission, 2);
                    $master->save();
            });
    }
}
