<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 快手订单自动同步专用的导入令牌校验。
 * 与现有 VerifyApiToken 隔离，避免共用同一个 token。
 * 支持两种传递方式：
 *   Authorization: Bearer <token>
 *   multipart/form-data 里的 token 字段
 */
class KsImportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app.ks_import_token');

        if ($expected === '') {
            return response()->json([
                'ok'    => false,
                'error' => 'KS_IMPORT_TOKEN_NOT_CONFIGURED',
            ], 503);
        }

        $given = $request->bearerToken() ?: (string) $request->input('token', '');

        if (!hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'UNAUTHORIZED'], 401);
        }

        return $next($request);
    }
}