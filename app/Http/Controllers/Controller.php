<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * 返回值处理
     *
     * @param Object $response
     * @return Array
     */
    protected static function resource($response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }
}
