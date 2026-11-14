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
