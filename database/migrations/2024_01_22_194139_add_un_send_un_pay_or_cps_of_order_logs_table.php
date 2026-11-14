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
        Schema::table('order_logs', function (Blueprint $table) {
            $table->bigInteger("unSend")->default(0)->comment("导入未发货数")->after("fail");
            $table->bigInteger("unPayOrCps")->default(0)->comment("导入未付款和无cpsID数")->after("fail");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_logs', function (Blueprint $table) {
            $table->dropColumn("unSend");
            $table->dropColumn("unPayOrCps");
        });
    }
};
