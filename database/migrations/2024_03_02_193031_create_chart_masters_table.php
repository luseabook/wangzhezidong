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
        Schema::create('chart_masters', function (Blueprint $table) {
            $table->id();
            $table->integer("master_id")->comment("所属达人");
            $table->string("date")->comment("日期");
            $table->bigInteger("order")->default(0)->comment("有效订单");
            $table->bigInteger("refund_order")->default(0)->comment("售后订单");
            $table->decimal("commission1", 12, 2)->default(0)->comment("第一次结算金额");
            $table->decimal("commission2", 12, 2)->default(0)->comment("第二次结算金额");
            $table->decimal("refund_commission", 12, 2)->default(0)->comment("售后金额");
            $table->decimal("cash", 12, 2)->default(0)->comment("提现金额");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_masters');
    }
};
