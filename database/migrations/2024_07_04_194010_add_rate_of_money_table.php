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
            $table->integer("rate")->default(0)->comment("分佣百分比");
            $table->integer("rate1")->default(0)->comment("第一次分佣百分比");
            $table->integer("rate2")->default(0)->comment("第二次分佣百分比");
            $table->integer("parent_rate")->default(0)->comment("父级分佣百分比");
            $table->tinyInteger("is_parent")->default(0)->comment("是否抽成");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('money', function (Blueprint $table) {
            $table->dropColumn("rate");
            $table->dropColumn("rate1");
            $table->dropColumn("rate2");
            $table->dropColumn("parent_rate");
            $table->dropColumn("is_parent");
        });
    }
};
