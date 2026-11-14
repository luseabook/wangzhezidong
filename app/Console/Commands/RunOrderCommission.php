<?php

namespace App\Console\Commands;

use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunOrderCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:run-order-commission {clear?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '运行订单分佣处理命令';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clear = $this->argument('clear');
        if($clear) {
            Money::truncate();
            $this->info('已清空流水记录记录');
            Order::query()->update([
                "importStatus" => 0,
                "cpsPayStatus" => 0,
                "commissionTotal" => 0,
                "commission1" => 0,
                "commission1Time" => null,
                "commission2" => 0,
                "commission2Time" => null,
            ]);
            $this->info('订单相关字段已修改');
            Master::query()->update([
                "commission_money" => 0,
            ]);
            $this->info('达人相关字段已修改');
        }

        Artisan::queue('create:order-commission');
        $this->info('订单分佣处理命令已加入队列');
    }
}
