<?php
declare(strict_types=1);

namespace app\common\controller\v1;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\service\AuthService;
use app\common\service\TokenService;
use app\common\support\MiniProgramContext;
use think\response\Json;

final class Auth extends BaseController
{
    public function wechatLogin(): Json
    {
        $input = $this->request->post(false);
        if (!is_array($input) || trim((string) ($input['code'] ?? '')) === '') {
            throw new BusinessException('微信登录 code 不能为空', 42200, 422);
        }

        $service = app()->make(AuthService::class);
        return $this->success($service->login($this->request, $input));
    }

    public function refresh(): Json
    {
        $refreshToken = trim((string) $this->request->post('refresh_token', ''));
        if ($refreshToken === '') {
            throw new BusinessException('refresh_token 不能为空', 42200, 422);
        }

        $program = MiniProgramContext::record($this->request);
        $tokens = app()->make(TokenService::class)->refresh($refreshToken, (int) $program->id);
        return $this->success($tokens);
    }

    public function logout(): Json
    {
        $token = trim((string) $this->request->header('authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $token, $matches)) {
            $token = trim($matches[1]);
        } else {
            $token = trim((string) $this->request->header('token', ''));
        }

        $program = MiniProgramContext::record($this->request);
        app()->make(TokenService::class)->revoke($token, (int) $program->id);
        return $this->success(null);
    }
}
