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
            $table->integer("commission_rate")->default(0)->comment("佣金率");
            $table->integer("commission1_rate")->default(0)->comment("佣金1率");
            $table->integer("commission2_rate")->default(0)->comment("佣金2率");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
            $table->dropColumn('commission1_rate');
            $table->dropColumn('commission2_rate');
        });
    }
};
