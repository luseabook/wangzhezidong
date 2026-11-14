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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("oid")->comment("订单id");
            $table->string("sellerOpenId")->comment("卖家id");
            $table->string("sellerNick")->nullable()->comment("卖家昵称");
            $table->bigInteger("expressFee")->default(0)->comment("运费");
            $table->bigInteger("discountFee")->default(0)->comment("促销减价、折扣价格");
            $table->bigInteger("totalFee")->comment("子订单商品总价");
            $table->integer("status")->default(0)->comment('订单状态：[0, "未知状态"], [10, "待付款"], [30, "已付款"], [40, "已发货"], [50, "已签收"], [70, "订单成功"], [80, "订单失败"];订单取消会转为“订单失败”状态');
            $table->integer("cpsType")->default(0)->comment('分销类型 0-全部 1-普通订单 2-分销订单');
            $table->timestamp("payTime")->nullable()->comment("付款时间");
            $table->timestamp("sendTime")->nullable()->comment("发货时间");
            $table->timestamp("refundTime")->nullable()->comment("发起退款时间");
            $table->timestamp("createTime")->nullable()->comment("创建时间");
            $table->timestamp("updateTime")->nullable()->comment("更新时间");
            $table->longText("orderDetail")->nullable()->comment("订单详情");

            $table->bigInteger("distributorId")->default(0)->comment("分销者id");
            $table->string("distributorName")->nullable()->comment("分销者昵称");
            $table->integer("cpsStatus")->default(0)->comment('分销单状态 30-已付款 50-已收货 60-已结算 80-已失效');
            $table->bigInteger("commissionRate")->default(0)->comment("分销率");
            $table->bigInteger("estimatedIncome")->default(0)->comment("分销金额（单位分）");
            $table->bigInteger("platformDpRate")->default(0)->comment("平台分销推广佣金分成比例。千分比");
            $table->integer("settlementBizType")->default(0)->comment('订单业务类型 1-快分销，2-聚力计划');
            $table->bigInteger("promoterServiceInCome")->default(0)->comment("达人接单服务收入（分）");
            $table->bigInteger("promoterExcitationInCome")->default(0)->comment("达人奖励收入（分）");
            $table->string("orderChannel")->nullable()->comment("出单渠道");
            $table->longText("cpsDetail")->nullable()->comment("分销订单详情");

            $table->bigInteger("skuId")->comment("快手商品skuid");
            $table->bigInteger("relSkuId")->comment("服务商商品skuid");
            $table->string("skuNick")->nullable()->comment("sku编码");
            $table->bigInteger("itemId")->nullable()->comment("商品id");
            $table->bigInteger("relItemId")->nullable()->comment("服务商商品id");
            $table->bigInteger("originalPrice")->default(0)->comment("商品促销前单价快照，单位为分");
            $table->bigInteger("goodDiscountFee")->default(0)->comment("折扣金额，单位为分");
            $table->bigInteger("price")->default(0)->comment("商品单价快照，单位为分");
            
            $table->integer("cpsPayStatus")->default(0)->comment("结算状态 0待结算 1第一次已结算 2第二次已结算");
            $table->timestamps();

            $table->index("oid");
            $table->index("sellerOpenId");
            $table->index("distributorId");
            $table->index("skuId");
            $table->comment("订单表");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
