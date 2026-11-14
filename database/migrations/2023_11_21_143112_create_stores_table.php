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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string("open_id")->nullable()->comment("open_id");
            $table->string("access_token")->nullable()->comment("access_token");
            $table->string("refresh_token")->nullable()->comment("refresh_token");
            $table->timestamp("expires_in")->nullable()->comment("access_token过期时间");
            $table->timestamp("refresh_token_expires_in")->nullable()->comment("refresh_token过期时间");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
