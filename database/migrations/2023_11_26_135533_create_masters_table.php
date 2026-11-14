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
        Schema::create('masters', function (Blueprint $table) {
            $table->id();
            $table->string("username")->unique()->comment("账户");
            $table->string("password")->comment("密码");
            $table->string("remember_token")->nullable()->comment("记住我");
            $table->bigInteger("cash_money")->default(0)->comment("累计提现金额");
            $table->timestamps();

            $table->comment("达人表");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masters');
    }
};
