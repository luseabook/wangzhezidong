<?php

namespace App\Console\Commands;

use App\Models\Cash;
use App\Models\ChartMaster as ModelsChartMaster;
use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ChartMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:chart-master {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '达人数据统计';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? date('Y-m-d');
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();
        $this->info("开始统计达人数据 " . $date);
        Master::query()
            ->get()
            ->each(function ($master) use ($date, $start, $end) {
                $distributor_ids = Distributor::query()
                    ->where("master_id", $master->id)
                    ->where("status", 1)
                    ->get()
                    ->pluck("distributorId")
                    ->toArray();

                $orderModel = Order::query()
                    ->with("product")
                    ->where("createTime", ">=", $start)
                    ->where("createTime", "<=", $end)
                    ->whereIn("distributorId", $distributor_ids);
                $order = (clone $orderModel)->ofCommission()
                    ->count();
                $refund_order = (clone $orderModel)->ofRefund()
                    ->count();

                $commission1 = (clone $orderModel)->ofCommission()
                    ->sum("commission1");
                $commission2 = (clone $orderModel)->ofCommission()
                    ->where("status", 70)
                    ->sum("commission2");

                $refund_commission1 = (clone $orderModel)->ofRefund()
                    ->sum("commission1");
                $refund_commission2 = (clone $orderModel)->ofRefund()
                    ->sum("commission2");
                $refund_commission = $refund_commission1 + $refund_commission2;

                $cash = Cash::query()
                    ->where("status", 1)
                    ->where("master_id", $master->id)
                    ->where("created_at", ">=", $start)
                    ->where("created_at", "<=", $end)
                    ->sum("amount");

                ModelsChartMaster::query()
                    ->updateOrCreate([
                        "master_id" => $master->id,
                        "date" => $date,
                    ], [
                        "order" => $order,
                        "refund_order" => $refund_order,
                        "commission1" => $commission1,
                        "commission2" => $commission2,
                        "refund_commission" => $refund_commission,
                        "cash" => $cash,
                    ]);
            });
        $this->info("结束统计达人数据 " . $date);
    }
}
