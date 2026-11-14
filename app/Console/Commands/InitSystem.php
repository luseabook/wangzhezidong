<?php

namespace App\Console\Commands;

use App\Models\Cash;
use App\Models\ChartMaster;
use App\Models\Distributor;
use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use App\Models\OrderImport;
use App\Models\OrderLog;
use App\Models\OrderStatistics;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Console\Command;

class InitSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:init-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始清理数据');
        Order::truncate();
        $this->info('Order clear');
        OrderLog::truncate();
        $this->info('OrderLog clear');
        (new OrderImport())->allTruncate();
        $this->info('OrderImport clear');
        Product::truncate();
        $this->info('Product clear');
        Seller::truncate();
        $this->info('Seller clear');
        Master::truncate();
        $this->info('Master clear');
        Distributor::truncate();
        $this->info('Distributor clear');
        Cash::truncate();
        $this->info('Cash clear');
        Money::truncate();
        $this->info('Money clear');
        ChartMaster::truncate();
        $this->info('ChartMaster clear');
        OrderStatistics::truncate();
        $this->info('OrderStatistics clear');
        $this->info('数据清理完成');
    }
}
