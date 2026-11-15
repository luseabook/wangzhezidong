<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * masters.cash_money 之前是 bigInteger，但写入的是提现金额（元，decimal）的求和，
 * 会把小数元舍入。改为 decimal(12,2)，与实际写入值一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('masters') || ! Schema::hasColumn('masters', 'cash_money')) {
            return;
        }

        Schema::table('masters', function (Blueprint $table) {
            $table->decimal('cash_money', 12, 2)
                ->default(0)
                ->comment('累计提现金额（元）')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('masters') || ! Schema::hasColumn('masters', 'cash_money')) {
            return;
        }

        // 回滚会把小数元舍入为整数元；正常部署不需要回滚。
        Schema::table('masters', function (Blueprint $table) {
            $table->bigInteger('cash_money')
                ->default(0)
                ->comment('累计提现金额')
                ->change();
        });
    }
};