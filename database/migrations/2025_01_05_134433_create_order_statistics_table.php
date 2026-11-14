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
        Schema::create('order_statistics', function (Blueprint $table) {
            $table->id();
            $table->string("date")->comment("日期");
            $table->decimal("allCommission", 12, 2)->comment("预估总佣金");
            $table->decimal("unCommission", 12, 2)->comment("预估剩余佣金");
            $table->decimal("doneCommission", 12, 2)->comment("已结算佣金");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_statistics');
    }
};
