<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Order;
use App\Observers\OrderObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // SQLite 预览环境兼容 MySQL 的 DATE_FORMAT()，生产 MySQL 不走这里。
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'DATE_FORMAT',
                function ($date, $format) {
                    if (! $date) {
                        return null;
                    }
                    $ts = strtotime((string) $date);
                    if ($ts === false) {
                        return null;
                    }
                    return date(str_replace('%', '', (string) $format), $ts);
                },
                2
            );
        }

        // 订单变更审计：自动同步与手工编辑都会留痕。
        // 观察者内部对写入做了容错，未跑迁移时不会影响订单导入。
        Order::observe(OrderObserver::class);
    }
}
