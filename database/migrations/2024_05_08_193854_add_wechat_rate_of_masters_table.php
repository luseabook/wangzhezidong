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
        Schema::table('masters', function (Blueprint $table) {
            $table->integer("wechat_rate1")->default(0)->comment("视频号第一次结算比例");
            $table->integer("wechat_rate2")->default(0)->comment("视频号第二次结算比例");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->dropColumn("wechat_rate1");
            $table->dropColumn("wechat_rate2");
        });
    }
};
