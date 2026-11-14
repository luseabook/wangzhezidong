<?php

namespace App\Console\Commands;

use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunChartMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:run-chart-master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '运行达人数据统计命令';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = new DatePeriod(
            new DateTime(now()->subHours(admin_setting("commission2_days", 600))->format("Y-m-d")),
            new DateInterval('P1D'),
            new DateTime(now()->format("Y-m-d"))
        );

        foreach ($period as $key => $value) {
            Artisan::call('create:chart-master', [
                'date' => $value->format('Y-m-d')
            ]);
        }

        Artisan::call('create:chart-distributor');
        Artisan::call('create:run-update-count-of-master', [
            'date' => date('Y-m-d')
        ]);
    }
}
