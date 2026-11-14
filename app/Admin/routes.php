<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Dcat\Admin\Admin;

Admin::routes();

Route::group([
    'prefix'     => config('admin.route.prefix'),
    'namespace'  => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');

    // 小店
    $router->resource("stores", 'StoreController');

    // 店铺管理
    $router->resource("sellers", 'SellerController');

    // 视频号小店
    $router->resource("wechat", 'WechatController');

    // 达人菜单
    $router->resource("master/menu", 'MasterMenuController');
    // 达人数据汇总统计
    $router->resource("master/chart", 'ChartMasterController')->only("index");
    // 达人数据统计
    $router->get("master/{id}/chart", 'ChartController@master');
    // 分销者
    $router->resource("master/distributor", 'DistributorController');
    // 提现
    $router->resource("master/cash", 'CashController')->except(['destroy']);
    // 达人
    $router->resource("master", 'MasterController');
    
    // 流水明细
    $router->resource("money", 'MoneyController')->only("index");

    // 产品
    $router->resource("product", 'ProductController');
    $router->resource("product2", 'Product2Controller');

    // 订单统计
    $router->resource("order/statistics", 'OrderStatisticController')->only("index");
    
    // 订单导入
    $router->get("order/import", 'OrderImportController@create');
    $router->get("order/import2", 'OrderImportController@create2');
    // 订单导入记录
    $router->resource("order/log", 'OrderLogController');

    // 订单变更记录（只读，用于追溯"何时、什么字段、从什么变成什么"）
    $router->resource("order/history", 'OrderHistoryController')->only(['index']);
    // 订单导出
    $router->get("export/order_import", 'OrderImportController@export');

    // 订单
    $router->resource("order", 'OrderController');

    // 设置
    // 网站设置
    $router->get('/settings/site', 'SettingsController@site')
        ->name('settings.site');
    // 结算设置
    $router->get('/settings/commission', 'SettingsController@commission')
        ->name('settings.commission');
    // 视频号小店设置
    $router->get('/settings/wechat', 'SettingsController@wechat')
        ->name('settings.wechat');
});
