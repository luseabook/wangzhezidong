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
        Schema::create('wechats', function (Blueprint $table) {
            $table->id();
            $table->string("name")->comment("小店名称");
            $table->string("appid")->comment("小店ID");
            $table->string("secret")->comment("小店Secret");
            $table->text("access_token")->nullable()->comment("access_token");
            $table->integer("expires_in")->nullable()->comment("凭证有效时间");
            $table->text("message")->nullable()->comment("错误信息");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wechats');
    }
};
