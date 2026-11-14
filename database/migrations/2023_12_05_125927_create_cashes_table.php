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
        Schema::create('cashes', function (Blueprint $table) {
            $table->id();
            $table->integer("master_id")->comment("达人ID");
            $table->decimal("amount", 10, 2)->default(0)->comment("金额");
            $table->tinyInteger("status")->default(0)->comment("状态 0待审核、1确认、2驳回");
            $table->string("remark")->nullable()->comment("驳回理由");
            $table->timestamp("apply_at")->nullable()->comment("申请时间");
            $table->timestamp("pay_at")->nullable()->comment("到账时间");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashes');
    }
};
