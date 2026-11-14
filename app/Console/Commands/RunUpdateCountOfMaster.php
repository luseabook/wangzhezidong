<?php

namespace App\Console\Commands;

use App\Models\Master;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunUpdateCountOfMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:run-update-count-of-master {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '所有达人按日期统计有效销售额、有效单数...';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        if(!$date) {
            $date = date('Y-m-d');
        }

        Master::query()
            ->get()
            ->each(function ($master) use ($date) {
                Artisan::call('create:update-count-of-master', [
                    'date' => $date,
                    "master_id" => $master->id,
                ]);
            });
    }
}
