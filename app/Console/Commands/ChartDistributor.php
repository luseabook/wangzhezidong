<?php

namespace App\Console\Commands;

use App\Models\Cash;
use App\Models\ChartDistributor as ModelsChartDistributor;
use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use Illuminate\Console\Command;

class ChartDistributor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:chart-distributor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '达人ID数据统计';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("开始统计达人ID数据");
        Distributor::query()
            ->where("status", 1)
            ->get()
            ->each(function ($distributor) {
                $model = Order::query()
                    ->where("distributorId", $distributor->distributorId)
                    ->whereNotNull("sendTime");

                $total_order = (clone $model)->count();

                $vaild_order = (clone $model)->ofNotRefund()->count();

                $model1 = (clone $model)->ofNotRefund();
                $totalFee = (clone $model1)->sum("totalFee");
                $expressFee = (clone $model1)->sum("expressFee");
                $total_pay = $totalFee - $expressFee;

                $total_commission = (clone $model)->ofCommission()->sum("commissionTotal");

                $model1 = (clone $model)->ofCommission()
                    ->where("commission1Time", ">=", now()->startOfDay())
                    ->where("commission1Time", "<=", now()->endOfDay());
                $commission1 = (clone $model1)->sum("commission1");
                $commission2 = (clone $model1)->sum("commission2");
                $today_can_cash = $commission1 + $commission2;

                ModelsChartDistributor::query()
                    ->updateOrCreate([
                        "distributorId" => $distributor->distributorId,
                    ], [
                        "total_order" => $total_order,
                        "vaild_order" => $vaild_order,
                        "total_pay" => $total_pay,
                        "total_commission" => $total_commission,
                        "today_can_cash" => $today_can_cash,
                    ]);
            });
        $this->info("结束统计达人ID数据");
    }
}
