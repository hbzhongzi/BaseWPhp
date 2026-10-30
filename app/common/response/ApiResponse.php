<?php
declare(strict_types=1);

namespace app\common\response;

use think\response\Json;

/**
 * 小程序 API 统一响应。
 */
final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'success'): Json
    {
        return self::make(0, $message, $data, 200);
    }

    public static function error(
        string $message = '请求失败',
        int $businessCode = 1,
        int $httpStatus = 400,
        mixed $data = null
    ): Json {
        return self::make($businessCode, $message, $data, $httpStatus);
    }

    private static function make(int $code, string $message, mixed $data, int $httpStatus): Json
    {
        $request = app()->request;
        $requestId = $request->middleware('request_id')
            ?: $request->header('X-Request-Id')
            ?: bin2hex(random_bytes(16));
        $request->withMiddleware(['request_id' => $requestId]);

        return json([
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
            'request_id' => $requestId,
        ], $httpStatus);
    }
}
