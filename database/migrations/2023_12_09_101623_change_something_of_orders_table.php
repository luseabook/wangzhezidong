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
            $table->bigInteger("skuId")->comment("快手商品skuid")->nullable()->change();
            $table->bigInteger("relSkuId")->comment("服务商商品skuid")->nullable()->change();
            $table->tinyInteger("type")->default(0)->comment("订单类型 0接口 1导入");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("type");
        });
    }
};
