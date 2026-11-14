<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Dcat\Admin\Admin;

Admin::routes();

Route::group([
    'prefix'     => config('master.route.prefix'),
    'namespace'  => config('master.route.namespace'),
    'middleware' => config('master.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');

     // 分销者
     $router->resource("distributor", 'DistributorController');

     // 订单列表
     $router->resource("order", 'OrderController');

     // 售后订单列表
    //  $router->resource("refund-order", 'OrderRefundController');

     // 提现明细
     $router->resource("cash", 'CashController')->only(['index']);

     // 流水明细
    $router->resource("money", 'MoneyController')->only("index");
});
