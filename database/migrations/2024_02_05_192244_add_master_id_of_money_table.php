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
        Schema::table('money', function (Blueprint $table) {
            $table->bigInteger("master_id")->default(0)->comment("所属达人");

            $table->index("master_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('money', function (Blueprint $table) {
            $table->dropColumn("master_id");
        });
    }
};
