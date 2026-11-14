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
            $table->bigInteger("parent_commission")->default(0)->comment("总抽成");
            $table->bigInteger("parent_commission1")->default(0)->comment("第一次结算抽成");
            $table->bigInteger("parent_commission2")->default(0)->comment("第二次结算抽成");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("parent_commission");
            $table->dropColumn("parent_commission1");
            $table->dropColumn("parent_commission2");
        });
    }
};
