<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger("importStatus")->default(0)->comment("导入状态 0 待处理 1第一次结算完成 2第二次结算完成");
            $table->bigInteger("commission1")->default(0)->comment("第一次结算佣金");
            $table->bigInteger("commission2")->default(0)->comment("第二次结算佣金");
            $table->timestamp("commission1Time")->nullable()->comment("第一次结算时间");
            $table->timestamp("commission2Time")->nullable()->comment("第二次结算时间");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("importStatus");
            $table->dropColumn("commission1");
            $table->dropColumn("commission2");
            $table->dropColumn("commission1Time");
            $table->dropColumn("commission2Time");
        });
    }
};
