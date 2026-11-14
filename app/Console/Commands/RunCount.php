<?php

namespace App\Console\Commands;

use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:run-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '运行各种数据统计命令';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("运行30天内的所有达人按日期的统计");
        $period = new DatePeriod(
            new DateTime(now()->subDays(30)->format("Y-m-d")),
            new DateInterval('P1D'),
            new DateTime(now()->format("Y-m-d"))
        );

        foreach ($period as $key => $value) {
            Artisan::call('create:run-update-count-of-master', [
                'date' => $value->format('Y-m-d')
            ]);
        }
    }
}
