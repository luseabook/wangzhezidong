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
            $table->integer("refundStatus")->default(0)->comment('退款状态 [10, "买家仅退款申请"] [11, "买家退货退款申请"] [20, "平台介入-买家仅退款申请"] [21, "平台介入-买家退货退款申请"] [22, "平台介入-已确认退货退款"] [30, "商品回寄信息待买家更新"] [40, "商品回寄信息待卖家确认"] [45, "待买家确认收货"] [50, "退款执行中"] [60, "退款成功"] [70, "退款失败"]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("refundStatus");
        });
    }
};
