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
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("distributorId")->comment("分销者id");
            $table->bigInteger("master_id")->default(0)->comment("所属达人");
            $table->tinyInteger("status")->default(0)->comment("状态 0待审核 1审核通过 2审核未通过");
            $table->timestamps();

            $table->index("distributorId");
            $table->index("master_id");

            $table->comment("分销人员表");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};
