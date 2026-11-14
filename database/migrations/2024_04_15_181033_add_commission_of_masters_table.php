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
            $table->decimal("commissioned", 12, 2)->default(0)->comment("已结算佣金");
            $table->decimal("cashed", 12, 2)->default(0)->comment("已提现佣金");
            $table->decimal("canCommission", 12, 2)->default(0)->comment("可提现佣金");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->dropColumn("commissioned");
            $table->dropColumn("cashed");
            $table->dropColumn("canCommission");
        });
    }
};
