<?php
declare(strict_types=1);

namespace app\mini\sample\controller\v1;

use app\common\response\ApiResponse;
use think\Request;
use think\response\Json;

/** 示例租户自己的控制器；真实项目中替换为具体业务接口。 */
final class Health
{
    public function index(Request $request): Json
    {
        return ApiResponse::success([
            'status'       => 'ok',
            'mini_program' => $request->middleware('mini_program'),
        ]);
    }
}
