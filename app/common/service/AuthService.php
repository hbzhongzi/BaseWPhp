<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\LoginLog;
use app\common\model\MiniProgramUser;
use app\common\model\User;
use app\common\support\Crypto;
use app\common\support\MiniProgramContext;
use think\Request;
use think\facade\Db;

final class AuthService
{
    private WechatService $wechat;
    private TokenService $tokens;

    public function __construct(WechatService $wechat, TokenService $tokens)
    {
        $this->wechat = $wechat;
        $this->tokens = $tokens;
    }

    public function login(Request $request, array $input): array
    {
        $program = MiniProgramContext::record($request);
        $config = MiniProgramContext::fromRequest($request);
        $wechatUser = $this->wechat->codeLogin($config, (string) $input['code']);
        $openidHash = Crypto::hash($wechatUser['openid']);

        return Db::transaction(function () use ($request, $program, $input, $wechatUser, $openidHash): array {
            $mapping = MiniProgramUser::where('program_id', $program->id)
                ->where('openid_hash', $openidHash)
                ->find();

            $isNew = !$mapping;
            if (!$mapping) {
                $user = User::create([
                    'nickname'   => $this->safeText($input['nickname'] ?? '') ?: '微信用户',
                    'avatar_url' => $this->safeText($input['avatar_url'] ?? ''),
                    'status'     => 1,
                ]);
                $mapping = MiniProgramUser::create([
                    'program_id'         => $program->id,
                    'user_id'            => $user->id,
                    'openid_ciphertext' => Crypto::encrypt($wechatUser['openid']),
                    'openid_hash'        => $openidHash,
                    'unionid_ciphertext' => $wechatUser['unionid'] ? Crypto::encrypt($wechatUser['unionid']) : null,
                    'last_login_at'      => date('Y-m-d H:i:s'),
                ]);
            } else {
                $user = User::where('id', $mapping->user_id)->where('status', 1)->findOrFail();
                $mapping->save(['last_login_at' => date('Y-m-d H:i:s')]);
            }

            $tokens = $this->tokens->issue((int) $program->id, (int) $user->id, $input['device_id'] ?? null);
            LoginLog::create([
                'program_id'  => $program->id,
                'user_id'     => $user->id,
                'request_id'  => $request->middleware('request_id'),
                'login_type'  => 'wechat_code',
                'result'      => 'success',
                'ip_hash'     => Crypto::hash((string) $request->ip()),
                'user_agent'  => substr((string) $request->header('user-agent', ''), 0, 512),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            return [
                'user' => $this->userData($user, $isNew),
                'token' => $tokens,
            ];
        });
    }

    public function userData(User $user, bool $isNew = false): array
    {
        return [
            'id'             => (int) $user->id,
            'nickname'       => (string) $user->nickname,
            'avatar_url'     => $user->avatar_url ?: null,
            'is_new_user'    => $isNew,
            'is_phone_bound' => !empty($user->mobile_hash),
            'created_at'     => $user->created_at,
        ];
    }

    private function safeText(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 255);
    }
}
