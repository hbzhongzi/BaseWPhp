<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\model\UserToken;

final class TokenService
{
    public function issue(int $programId, int $userId, ?string $deviceId = null): array
    {
        $family = bin2hex(random_bytes(16));
        $access = $this->create($programId, $userId, 'access', $family, $deviceId, 7200);
        $refresh = $this->create($programId, $userId, 'refresh', $family, $deviceId, 2592000);

        return [
            'access_token'  => $access['plain'],
            'refresh_token' => $refresh['plain'],
            'token_type'    => 'Bearer',
            'expires_in'    => 7200,
        ];
    }

    public function resolveAccess(string $plainToken, int $programId): array
    {
        $token = UserToken::where('token_hash', hash('sha256', $plainToken))
            ->where('token_type', 'access')
            ->where('program_id', $programId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();

        if (!$token) {
            throw new BusinessException('登录凭证无效或已过期', 40101, 401);
        }

        $user = User::where('id', $token->user_id)->where('status', 1)->find();
        if (!$user) {
            throw new BusinessException('用户不存在或已停用', 40101, 401);
        }

        return ['token' => $token, 'user' => $user];
    }

    public function refresh(string $plainToken, int $programId): array
    {
        $token = UserToken::where('token_hash', hash('sha256', $plainToken))
            ->where('token_type', 'refresh')
            ->where('program_id', $programId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();

        if (!$token) {
            throw new BusinessException('刷新凭证无效或已过期', 40101, 401);
        }

        $token->save(['revoked_at' => date('Y-m-d H:i:s')]);
        return $this->issue($programId, (int) $token->user_id, $token->device_id);
    }

    public function revoke(string $plainToken, int $programId): void
    {
        UserToken::where('token_hash', hash('sha256', $plainToken))
            ->where('program_id', $programId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    private function create(
        int $programId,
        int $userId,
        string $type,
        string $family,
        ?string $deviceId,
        int $ttl
    ): array {
        $plain = bin2hex(random_bytes(32));

        UserToken::create([
            'program_id'  => $programId,
            'user_id'     => $userId,
            'token_hash'  => hash('sha256', $plain),
            'token_family'=> $family,
            'token_type'  => $type,
            'device_id'   => $deviceId,
            'expires_at'  => date('Y-m-d H:i:s', time() + $ttl),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return ['plain' => $plain];
    }
}
