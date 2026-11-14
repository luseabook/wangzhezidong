<?php

namespace App\Console\Commands;

use App\Jobs\UpdateMasterCommission as JobsUpdateMasterCommission;
use Illuminate\Console\Command;

class UpdateMasterCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:update-master-commission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新达人表中已结算、已提现、可提现佣金字段';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        JobsUpdateMasterCommission::dispatch();

        $this->info("更新任务已加入队列");
    }
}
