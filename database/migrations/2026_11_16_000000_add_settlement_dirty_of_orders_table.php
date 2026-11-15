<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders 新增 settlement_dirty：订单金额/商品/归属发生变化时标记待重算。
 *
 * 由 OrderObserver 在 updating 时置 1，create:order-commission 的
 * recalcDirtyOrders() 负责冲正旧账本、重置结算字段并重新结算。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'settlement_dirty')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('settlement_dirty')
                ->default(0)
                ->comment('结算输入变更待重算 1是');
            $table->index('settlement_dirty');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'settlement_dirty')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['settlement_dirty']);
            $table->dropColumn('settlement_dirty');
        });
    }
};