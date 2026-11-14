<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // $response = Http::get("https://api.weixin.qq.com/cgi-bin/token", [
    //     "grant_type" => "client_credential",
    //     "appid" => env("WECHAT_APP_ID"),
    //     "secret" => env("WECHAT_APP_SECRET")
    // ]);

    // $data = $response->json();

    // $response = Http::post("https://api.weixin.qq.com/channels/ec/order/list/get?access_token=".env("WECHAT_ACCESS_TOKEN"), [
    //     "create_time_range" => [
    //         "start_time" => now()->subDay()->startOfDay()->getTimeStamp(),
    //         "end_time" => now()->getTimeStamp()
    //     ],
    //     "page_size" => 10
    // ]);

    // return $response->json();

    // $response = Http::post("https://api.weixin.qq.com/channels/ec/order/get?access_token=".env("WECHAT_ACCESS_TOKEN"), [
    //     "order_id" => "3719029950572004864"
    // ]);

    // return $response->json();

    return redirect("/master");
    // return view('welcome');
});

Route::get("/api/ks", "App\Http\Controllers\KsController@oauth");
