<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给导入批次加「来源」标记：0=手动 1=自动。
 * 存量行默认 0（手动），与既有数据语义一致，无需回填。
 *
 * 设计说明：来源属于「一次导入批次」而非「一个订单」。
 * 因为 DealImportOrder 用 Order::updateOrCreate(['oid']) 幂等覆盖，
 * 若把来源存在 orders 表上，会被后一次导入覆盖，丢失历史语义。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_logs')) {
            return;
        }
        // 幂等：某些环境已手工加过该列，直接跳过
        if (Schema::hasColumn('order_logs', 'source')) {
            return;
        }

        Schema::table('order_logs', function (Blueprint $table) {
            $table->tinyInteger('source')
                ->default(0)
                ->comment('来源 0手动 1自动')
                ->after('file');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_logs') || !Schema::hasColumn('order_logs', 'source')) {
            return;
        }
        Schema::table('order_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};