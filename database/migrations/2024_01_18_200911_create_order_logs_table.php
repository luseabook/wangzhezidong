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
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->string("sellerOpenId")->comment("店铺id");
            $table->string("sellerNick")->nullable()->comment("店铺昵称");
            $table->string("file")->comment("文件");
            $table->bigInteger("total")->default(0)->comment("导入订单总数");
            $table->bigInteger("success")->default(0)->comment("导入成功数");
            $table->bigInteger("fail")->default(0)->comment("导入失败数");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
