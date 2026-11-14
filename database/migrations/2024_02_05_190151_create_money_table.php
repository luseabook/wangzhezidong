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
        Schema::create('money', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("oid")->comment("订单id");
            $table->tinyInteger("type")->comment("类型 1增加 2减少");
            $table->bigInteger("money")->comment("金额");
            $table->bigInteger("before_money")->comment("操作前金额");
            $table->bigInteger("after_money")->comment("操作后金额");
            $table->string("content")->nullable()->comment("描述");
            $table->timestamps();

            $table->index("oid");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money');
    }
};
