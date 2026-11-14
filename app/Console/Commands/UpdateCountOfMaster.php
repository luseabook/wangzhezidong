<?php

namespace App\Console\Commands;

use App\Models\Cash;
use App\Models\CountOfMaster;
use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use App\Traits\CashTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UpdateCountOfMaster extends Command
{
    use CashTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:update-count-of-master {date} {master_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '达人按日期统计有效销售额、有效单数...';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        $master_id = $this->argument('master_id');
        if(!$date) {
            $this->error("日期不能为空");
            return;
        }
        if(!$master_id) {
            $this->error("达人ID不能为空");
            return;
        }

        $this->info("开始更新达人{$master_id}按日期统计有效销售额、有效单数... {$date}");

        $distributor_ids = Distributor::query()
                ->where("master_id", $master_id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $orderModel = Order::query()
            ->whereIn('distributorId', $distributor_ids)
            ->ofCommission()
            ->where("payTime", ">=", $date . " 00:00:00")
            ->where("payTime", "<=", $date . " 23:59:59");

        $total_order = (clone $orderModel)->count();
        $total_sale = (clone $orderModel)->sum('totalFee');

        $allCommission = $this->allCommissionByModel((clone $orderModel));
        $doneCommission = $this->doneCommissionByModel((clone $orderModel));
        $unCommission = bcsub($allCommission, $doneCommission, 2);
        $total_cash = Cash::query()->where('master_id', $master_id)
            ->where('status', 1)
            ->where('apply_at', ">=", $date . " 00:00:00")
            ->where('apply_at', "<=", $date . " 23:59:59")
            ->sum('amount');

        CountOfMaster::query()->updateOrCreate([
            "date" => $date,
            "master_id" => $master_id,
        ], [
            "total_order" => $total_order,
            "total_sale" => $total_sale,
            "total_commission" => $allCommission * 100,
            "total_uncommission" => $unCommission * 100,
            "total_cash" => $total_cash * 100,
        ]);
    }
}
