<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\support\ProgramLogger;
use Closure;
use think\Request;
use think\Response;

/** 为每个请求建立可关联的请求 ID。 */
final class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId)) {
            $requestId = bin2hex(random_bytes(16));
        }

        $startedAt = microtime(true);
        $request->withMiddleware([
            'request_id'          => $requestId,
            'request_started_at'  => $startedAt,
        ]);

        $response = $next($request);
        ProgramLogger::request($request, $response, $startedAt);

        return $response->header(['X-Request-Id' => $requestId]);
    }
}
