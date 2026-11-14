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
        Schema::create('chart_distributors', function (Blueprint $table) {
            $table->id();
            $table->string("distributorId")->comment("达人ID");
            $table->integer("total_order")->default(0)->comment("累计成交单数");
            $table->integer("vaild_order")->default(0)->comment("累计有效单数");
            $table->integer("total_pay")->default(0)->comment("累计成交金额");
            $table->integer("total_commission")->default(0)->comment("累计预估佣金");
            $table->integer("today_can_cash")->default(0)->comment("今日可提现");
            $table->integer("cashed")->default(0)->comment("累计已提现");
            $table->timestamps();

            $table->index("distributorId");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_distributors');
    }
};
