<?php
declare(strict_types=1);

use app\common\controller\v1\Auth;
use app\common\controller\v1\Bootstrap;
use app\common\controller\v1\File;
use app\common\controller\v1\Health;
use app\common\controller\v1\User;
use app\common\response\ApiResponse;
use app\middleware\Authenticate;
use app\middleware\ResolveMiniProgram;
use think\facade\Route;

// 公共接口为每个已注册小程序生成同一套路由；业务接口继续放在各项目路由文件中。
foreach (array_keys(config('mini_programs.programs', [])) as $programKey) {
    $public = [[ResolveMiniProgram::class, [$programKey]]];
    $protected = [[ResolveMiniProgram::class, [$programKey]], [Authenticate::class, []]];

    Route::group('api/' . $programKey . '/v1', function () use ($public, $protected): void {
        Route::get('health', [Health::class, 'index'])->middleware($public);
        Route::get('app/bootstrap', [Bootstrap::class, 'index'])->middleware($public);
        Route::post('auth/wechat-login', [Auth::class, 'wechatLogin'])->middleware($public);
        Route::post('auth/refresh', [Auth::class, 'refresh'])->middleware($public);
        Route::post('auth/logout', [Auth::class, 'logout'])->middleware($protected);
        Route::get('users/me', [User::class, 'me'])->middleware($protected);
        Route::patch('users/me', [User::class, 'update'])->middleware($protected);
        Route::post('files/images', [File::class, 'image'])->middleware($protected);
        Route::get('files/<fileId>', [File::class, 'show'])->middleware($protected);
        Route::delete('files/<fileId>', [File::class, 'delete'])->middleware($protected);
    });

    $programRoute = __DIR__ . '/mini_programs/' . $programKey . '.php';
    if (is_file($programRoute)) {
        include_once $programRoute;
    }
}

// 未注册项目也返回稳定的项目错误码，而不是框架 HTML 404 页面。
Route::any('api/<programKey>/v1/<path>', function (string $programKey, string $path) {
    if (is_array(config('mini_programs.programs.' . $programKey))) {
        return ApiResponse::error('接口不存在', 40400, 404);
    }

    return ApiResponse::error('小程序未注册或已停用', 40401, 404);
});

// 保留框架演示路由，生产环境建议删除。
Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});
