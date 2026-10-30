<?php
declare(strict_types=1);

namespace app\common\controller\v1;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\service\AuthService;
use think\response\Json;

final class User extends BaseController
{
    public function me(): Json
    {
        $user = $this->request->middleware('auth_user');
        return $this->success(app()->make(AuthService::class)->userData($user));
    }

    public function update(): Json
    {
        $input = $this->request->post(false);
        if (!is_array($input)) {
            throw new BusinessException('请求数据格式错误', 40000, 400);
        }

        $allowed = array_intersect_key($input, array_flip(['nickname', 'avatar_url']));
        if (array_key_exists('nickname', $allowed)) {
            $allowed['nickname'] = mb_substr(trim((string) $allowed['nickname']), 0, 64);
            if ($allowed['nickname'] === '') {
                throw new BusinessException('昵称不能为空', 42200, 422);
            }
        }
        if (array_key_exists('avatar_url', $allowed)) {
            $allowed['avatar_url'] = mb_substr(trim((string) $allowed['avatar_url']), 0, 512);
        }
        if (!$allowed) {
            throw new BusinessException('没有可修改的资料字段', 42200, 422);
        }

        $user = $this->request->middleware('auth_user');
        $user->save($allowed);
        return $this->success(app()->make(AuthService::class)->userData($user));
    }
}
