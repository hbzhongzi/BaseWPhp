<?php
namespace app;

use app\common\exception\BusinessException;
use app\common\response\ApiResponse;
use app\common\support\ProgramLogger;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if (str_starts_with(trim($request->path(), '/'), 'api/')) {
            ProgramLogger::exception($request, $e);

            if ($e instanceof BusinessException) {
                return ApiResponse::error($e->getMessage(), $e->getBusinessCode(), $e->getHttpStatus());
            }

            if ($e instanceof ValidateException) {
                return ApiResponse::error((string) $e->getError(), 42200, 422);
            }

            if ($e instanceof HttpException) {
                $status = $e->getStatusCode();
                return ApiResponse::error($e->getMessage() ?: '请求失败', $status * 100, $status);
            }

            return ApiResponse::error('服务内部错误', 50000, 500);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
