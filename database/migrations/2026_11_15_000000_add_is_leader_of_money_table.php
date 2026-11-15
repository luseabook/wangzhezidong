<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * money 表新增 is_leader：区分团长分佣与上级抽成。
 *
 * 这两类流水此前都用 is_parent=1，导致 leaderCommissioned() 把上级抽成
 * 也算进可提现，与 parentCommissioned() 重复。新增独立标记后：
 * - is_leader=1：团长分佣（带商品 itemId）
 * - is_leader=0：上级抽成 / 普通分佣
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('money')) {
            return;
        }

        if (! Schema::hasColumn('money', 'is_leader')) {
            Schema::table('money', function (Blueprint $table) {
                $table->tinyInteger('is_leader')
                    ->default(0)
                    ->comment('是否团长分佣 1是');
            });
        }

        // 存量回填：团长分佣是 is_parent=1 且带商品 itemId 的流水；
        // 上级抽成的 itemId 默认为 0。
        DB::table('money')
            ->where('is_parent', 1)
            ->where('itemId', '>', 0)
            ->update(['is_leader' => 1]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('money') || ! Schema::hasColumn('money', 'is_leader')) {
            return;
        }

        Schema::table('money', function (Blueprint $table) {
            $table->dropColumn('is_leader');
        });
    }
};