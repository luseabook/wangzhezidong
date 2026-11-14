<?php

use App\Http\Middleware\VerifyApiToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::group([
    'prefix'     => 'v1',
    'namespace'  => 'App\\Http\\Controllers',
    'middleware' => [
        VerifyApiToken::class,
    ],
], function (Router $router) {
    $router->get("master", "ApiController@master");
    $router->get("distributor", "ApiController@distributor");
    $router->get("product", "ApiController@product");
});

/*
|--------------------------------------------------------------------------
| 快手订单自动同步（本地 KsSync 调用）
|--------------------------------------------------------------------------
| 只做「收文件 + 建导入记录 + 派发既有队列」，不改动任何导入解析逻辑。
*/
Route::group([
    'prefix'     => 'ks',
    'middleware' => [
        \App\Http\Middleware\KsImportToken::class,
    ],
], function (Router $router) {
    $router->post("import", [\App\Http\Controllers\Api\KsImportController::class, "store"]);
    $router->get("import/{id}", [\App\Http\Controllers\Api\KsImportController::class, "show"])
        ->whereNumber("id");
    $router->get("shop/{id}", [\App\Http\Controllers\Api\KsImportController::class, "shop"])
        ->whereNumber("id");
});
