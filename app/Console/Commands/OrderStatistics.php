<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatistics as ModelsOrderStatistics;
use App\Traits\CashTrait;
use Illuminate\Console\Command;

class OrderStatistics extends Command
{
    use CashTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:order-statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每日订单统计';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("开始统计订单数据");
        $query = Order::query();

        $allCommission = $this->allCommissionByModel((clone $query));
        $doneCommission = $this->doneCommissionByModel((clone $query));
        $unCommission = bcsub($allCommission, $doneCommission, 2);

        ModelsOrderStatistics::query()
            ->updateOrCreate([
                "date" => now()->format("Y-m-d"),
            ], [
                "allCommission" => $allCommission,
                "doneCommission" => $doneCommission,
                "unCommission" => $unCommission,
            ]); 


        $this->info("订单数据统计完成");
    }
}
