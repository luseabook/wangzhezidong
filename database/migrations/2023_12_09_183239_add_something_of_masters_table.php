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
            $table->tinyInteger("type")->default(0)->comment("收款方式 1支付宝 2银行");
            $table->string("realname")->nullable()->comment("真实姓名");
            $table->string("account")->nullable()->comment("收款账户");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->dropColumn("type");
            $table->dropColumn("realname");
            $table->dropColumn("account");
        });
    }
};
