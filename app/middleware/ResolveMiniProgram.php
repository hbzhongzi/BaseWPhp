<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 根据路由绑定的小程序标识解析已注册的项目上下文。
 * 此中间件只做项目识别，不等同于用户认证或数据授权。
 */
final class ResolveMiniProgram
{
    public function handle(Request $request, Closure $next, ?string $programKey = null): Response
    {
        // 路由组通常直接绑定中间件；从 /api/{programKey}/... 路径解析项目键。
        $programKey ??= explode('/', trim($request->path(), '/'))[1] ?? '';

        $program = config('mini_programs.programs.' . $programKey);

        if (!is_array($program) || !($program['enabled'] ?? false)) {
            return json([
                'code'      => 40401,
                'message'   => '小程序未注册或已停用',
                'data'      => null,
                'timestamp' => time(),
            ], 404);
        }

        $request->withMiddleware([
            'mini_program' => [
                'key'      => $programKey,
                'name'     => $program['name'] ?? $programKey,
                'features' => $program['features'] ?? [],
            ],
        ]);

        return $next($request);
    }
}
