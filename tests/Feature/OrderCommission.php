<?php

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use App\Models\Product;

function createMaster()
{
    $master = Master::factory(1)->create()->first();

    expect($master->id)->toBeInt();
    expect($master->username)->toBeString();

    return $master;
}

function createParentMaster()
{
    $parentMaster = createMaster();
    $master = Master::factory(1)->create([
        'parent_id' => $parentMaster->id,
        'parent_rate' => 10,
    ])->first();

    $distributor = createDistributor($master);

    expect($master->id)->toBeInt();
    expect($master->parent_id)->toBe($parentMaster->id);
    expect($master->parent_rate)->toBe(10);

    return $distributor;
}

function createDistributor($master)
{
    $distributor = Distributor::factory(1)->create([
        'master_id' => $master->id,
    ])->first();

    expect($master->id)->toBeInt();
    expect($distributor->id)->toBeInt();
    expect($distributor->master_id)->toBe($master->id);

    return $distributor;
}

function createProduct()
{
    $product = Product::factory(1)->create()->first();

    expect($product->id)->toBeInt();
    expect($product->itemId)->toBeInt();
    expect($product->status)->toBe(1);
    expect($product->commissionRate)->toBeInt();

    return $product;
}

function createOrder($distributor = null)
{
    if(!$distributor) {
        $master = createMaster();
        $distributor = createDistributor($master);
    }

    $product = createProduct();
    $order = Order::factory(1)->create([
        "distributorId" => $distributor->distributorId,
        "distributorName" => $distributor->distributorName,
        "itemId" => $product->itemId,
        "price" => $product->price,
        "totalFee" => $product->price,
        "num" => 1,
    ])->first();

    expect($order->id)->toBeInt();
    expect($order->distributorId)->toBe($distributor->distributorId);
    expect($order->itemId)->toBe($product->itemId);

    return $order;
}

it('create master', function () {
    createMaster();
});

it('create distributor', function () {
    $master = createMaster();
    createDistributor($master);
});

it('create product', function () {
    createProduct();
});

it('create order', function () {
    createOrder();
});

it('order commission', function() {
    $order = createOrder();

    $this->artisan('create:order-commission')->assertSuccessful();

    $order = Order::find($order->id);
    expect($order->commissionTotal)->toBe($order->commission1 + $order->commission2);
    expect($order->rate)->toBe((int)($order->rate1 + $order->rate2));
    expect($order->commission_rate)->toBe((int)($order->commission1_rate + $order->commission2_rate));
    expect($order->rate)->toBe((int)($order->commission_rate));
});

it('create parent master', function() {
    createParentMaster();
});

it('order commission and master parent commission', function() {
    $distributor = createParentMaster();
    $order = createOrder($distributor);

    $this->artisan('create:order-commission')->assertSuccessful();

    $order = Order::find($order->id);
    expect($order->commissionTotal)->toBe($order->commission1 + $order->commission2);
    expect($order->parent_commission)->toBe($order->parent_commission1 + $order->parent_commission2);
    expect($order->rate)->toBe((int)($order->rate1 + $order->rate2));
    expect($order->rate)->not->toBe($order->commission_rate);
    expect($order->commission_rate)->toBe((int)($order->commission1_rate + $order->commission2_rate));
    expect(intval(($order->totalFee - $order->expressFee) * $order->rate / 100))
        ->toBe((int)($order->commissionTotal + $order->parent_commission));
});