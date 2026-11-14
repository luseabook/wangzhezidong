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
        Schema::create('count_of_masters', function (Blueprint $table) {
            $table->id();
            $table->integer("master_id")->comment("达人ID");
            $table->string("date")->comment("日期");
            $table->integer("total_sale")->default(0)->comment("有效销售额");
            $table->integer("total_order")->default(0)->comment("有效单数");
            $table->integer("total_commission")->default(0)->comment("预估总佣金");
            $table->integer("total_uncommission")->default(0)->comment("预估剩余佣金");
            $table->integer("total_cash")->default(0)->comment("已结算佣金");
            $table->timestamps();

            $table->index("master_id");
            $table->index("date");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('count_of_masters');
    }
};
