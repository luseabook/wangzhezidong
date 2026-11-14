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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("skuId")->comment("快手商品skuid");
            $table->bigInteger("relSkuId")->comment("服务商商品skuid");
            $table->string("skuDesc")->nullable()->comment("sku商品规格快照");
            $table->string("skuNick")->nullable()->comment("sku编码");
            $table->bigInteger("itemId")->nullable()->comment("商品id");
            $table->bigInteger("relItemId")->nullable()->comment("服务商商品id");
            $table->string("itemTitle")->nullable()->comment("商品名称");
            $table->text("itemLinkUrl")->nullable()->comment("商品链接");
            $table->text("itemPicUrl")->nullable()->comment("商品图片地址");
            $table->bigInteger("price")->default(0)->comment("商品单价快照，单位为分");
            $table->integer("itemType")->default(0)->comment("1自建商品 2 闪电购商品");
            
            $table->integer("status")->default(0)->comment("是否参与结算");
            $table->bigInteger("commissionRate")->default(0)->comment("自定义商品参与结算分销率");
            $table->timestamps();

            $table->index("skuId");
            $table->index("relSkuId");
            $table->index("itemId");

            $table->comment("商品表");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
