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
        Schema::create('wechat_logs', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger("type")->default(0)->comment("类型 0订单列表 1订单详情");
            $table->text("wechat")->comment("视频号小店");
            $table->text("response")->comment("返回数据");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wechat_logs');
    }
};
