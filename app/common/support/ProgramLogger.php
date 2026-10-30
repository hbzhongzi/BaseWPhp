<?php
declare(strict_types=1);

namespace app\common\support;

use app\common\exception\BusinessException;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use Throwable;

/**
 * 按小程序隔离写入 API 访问日志。日志只包含路由元数据和状态，不记录请求/响应正文。
 */
final class ProgramLogger
{
    public static function request(Request $request, Response $response, float $startedAt): void
    {
        if (!self::isApiRequest($request)) {
            return;
        }

        self::write($request, [
            'event'       => 'request',
            'method'      => strtoupper((string) $request->method()),
            'route'       => self::route($request),
            'status'      => $response->getCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
    }

    public static function exception(Request $request, Throwable $exception): void
    {
        if (!self::isApiRequest($request)) {
            return;
        }

        if ($exception instanceof BusinessException) {
            $status = $exception->getHttpStatus();
        } elseif ($exception instanceof ValidateException) {
            $status = 422;
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
        } else {
            $status = 500;
        }

        $errorCode = $exception instanceof BusinessException
            ? $exception->getBusinessCode()
            : (int) $exception->getCode();

        self::write($request, [
            'event'         => 'exception',
            'method'        => strtoupper((string) $request->method()),
            'route'         => self::route($request),
            'status'        => $status,
            'duration_ms'   => self::duration($request),
            'error_type'    => get_class($exception),
            'error_code'    => $errorCode,
            // 不记录异常原始 message，避免 SQL、用户输入或凭据意外进入日志。
            'error_summary' => 'API 请求处理异常',
        ]);
    }

    private static function write(Request $request, array $entry): void
    {
        try {
            if ((bool) config('log.close', false) || !(bool) config('log.program.enabled', true)) {
                return;
            }

            $programKey = self::programKey($request);
            $root = rtrim((string) config('log.program.path', app()->getRuntimePath() . 'program_logs'), '/\\');
            if ($root === '') {
                return;
            }

            $directory = $root . DIRECTORY_SEPARATOR . $programKey;
            if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
                self::writeFailureSignal();
                return;
            }

            $entry = array_merge([
                'timestamp'   => date(DATE_ATOM),
                'program_key' => $programKey,
                'request_id'  => self::requestId($request),
            ], $entry);

            $json = json_encode(
                $entry,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($json === false) {
                self::writeFailureSignal();
                return;
            }

            $file = $directory . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            if (file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                self::writeFailureSignal();
                return;
            }

            // Keep application logs private to the PHP process and its operator group.
            @chmod($directory, 0770);
            @chmod($file, 0660);
        } catch (Throwable) {
            // Logging must never turn an otherwise valid API response into a failure.
            self::writeFailureSignal();
        }
    }

    private static function programKey(Request $request): string
    {
        $context = $request->middleware('mini_program');
        $candidate = is_array($context) ? (string) ($context['key'] ?? '') : '';

        if ($candidate === '') {
            $segments = explode('/', trim($request->path(), '/'));
            $candidate = ($segments[0] ?? '') === 'api' ? (string) ($segments[1] ?? '') : '';
        }

        return preg_match('/^[a-z0-9_]{1,64}$/', $candidate)
            && is_array(config('mini_programs.programs.' . $candidate))
            ? $candidate
            : 'unknown';
    }

    private static function requestId(Request $request): string
    {
        $requestId = (string) $request->middleware('request_id');
        if ($requestId === '') {
            $requestId = (string) $request->header('X-Request-Id', '');
        }

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId)
            ? $requestId
            : 'unavailable';
    }

    private static function route(Request $request): string
    {
        $rule = $request->rule();
        $route = is_object($rule) && method_exists($rule, 'getRule')
            ? (string) $rule->getRule()
            : 'unmatched';

        // Route templates are preferred; avoid falling back to raw URLs or query strings.
        $route = preg_replace('/[\x00-\x1F\x7F]/u', '', $route) ?? '';
        return substr($route !== '' ? $route : 'unmatched', 0, 300);
    }

    private static function duration(Request $request): float
    {
        $startedAt = $request->middleware('request_started_at');
        return is_numeric($startedAt)
            ? round((microtime(true) - (float) $startedAt) * 1000, 2)
            : 0.0;
    }

    private static function isApiRequest(Request $request): bool
    {
        return str_starts_with(trim($request->path(), '/'), 'api/');
    }

    private static function writeFailureSignal(): void
    {
        error_log('[program-log] unable to append API log record');
    }
}
