<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        # 每5分钟执行一次
        # 订单分佣处理
        // php artisan create:run-order-commission
        $schedule->command('create:run-order-commission')->everyThirtyMinutes();

        # 每天执行一次
        # 达人数据统计
        // php artisan create:run-chart-master
        $schedule->command('create:run-chart-master')->dailyAt("1:00");
        # 订单统计
        $schedule->command('create:order-statistics')->dailyAt("2:00");
        # 运行各种数据统计命令
        $schedule->command('create:run-count')->dailyAt("3:00");

        # 每小时执行一次
        # 视频号小店订单更新
        // php artisan app:spider-wechat-order
        $schedule->command('app:spider-wechat-order')->hourly();

        # 每小时执行一次
        # 更新达人表中已结算、已提现、可提现佣金字段
        // php artisan create:update-master-commission
        $schedule->command('create:update-master-commission')->hourly();

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
