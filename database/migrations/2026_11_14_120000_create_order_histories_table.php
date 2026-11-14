<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单变更历史：每条记录 = 一个字段的一次变化。
 *
 * 设计要点：
 *  - 用 oid（业务单号）而不是外键关联，订单被删掉后历史仍然可查，符合"有据可依"
 *  - source 区分 0=手工 1=自动导入 2=系统任务
 *  - operator 记录是哪个管理员改的（手工编辑时）
 *  - order_log_id 关联导入批次，自动同步产生的变更都能追到是哪一批
 *
 * 记录由 OrderObserver 写入（只记值真正变化的字段，重复导入不会产生噪音）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('oid')->comment('业务订单号');
            $table->unsignedBigInteger('order_id')->nullable()->comment('orders.id 快照');
            $table->string('field', 64)->comment('变更字段');
            $table->string('old_value', 512)->nullable();
            $table->string('new_value', 512)->nullable();
            $table->tinyInteger('source')->default(0)->comment('0手工 1自动导入 2系统任务');
            $table->unsignedBigInteger('order_log_id')->nullable()->comment('导入批次');
            $table->string('operator', 64)->nullable()->comment('操作人');
            $table->timestamp('changed_at')->nullable()->comment('变更时间');

            $table->index(['oid', 'changed_at'], 'idx_hist_oid');
            $table->index(['changed_at'], 'idx_hist_time');
            $table->index(['field', 'changed_at'], 'idx_hist_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};