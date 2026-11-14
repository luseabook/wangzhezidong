<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KsController extends Controller
{

    public function oauth(Request $request)
    {
        if($code = $request->input("code")) {
            $response = $this->code2AccessToken($code);

            Log::info($response);
            
            if($response["result"] == 1) {
                Store::query()
                    ->updateOrCreate([
                        "open_id" => $response['open_id'],
                    ], [
                        "access_token" => $response['access_token'],
                        "refresh_token" => $response['refresh_token'],
                        "expires_in" => Carbon::now()->addDays(2),
                        "refresh_token_expires_in" => Carbon::now()->addDays(180),
                    ]);
                
                return "授权成功";
            } else {
                return $response["error_msg"];
            }
        }
    }

    /**
     * 获取用户授权token.
     *
     * @param string $code
     * @return array
     */
    private function code2AccessToken(string $code)
    {
        $query = [
            'app_id' => env("KS_APP_ID"),
            'app_secret' => env("KS_APP_SECRET"),
            'code' => $code,
            'grant_type' => 'code',
        ];

        $url = "https://openapi.kwaixiaodian.com/oauth2/access_token";

        $response = Http::get($url, $query);

        return self::resource($response);
    }

    /**
     * Refresh Access Token
     *
     * @param string $refresh_token
     * @return array
     */
    public function refreshAccessToken(string $refresh_token)
    {
        $query = [
            'app_id' => env("KS_APP_ID"),
            'app_secret' => env("KS_APP_SECRET"),
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ];

        $url = "https://openapi.kwaixiaodian.com/oauth2/refresh_token";
        
        $response = Http::post($url, $query);

        return self::resource($response);
    }
}
