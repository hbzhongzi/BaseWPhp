<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\BusinessException;
use RuntimeException;

final class WechatService
{
    public function codeLogin(array $programConfig, string $code): array
    {
        $appid = trim((string) env((string) ($programConfig['appid_env'] ?? ''), ''));
        $secret = trim((string) env((string) ($programConfig['secret_env'] ?? ''), ''));

        if ($appid === '' || $secret === '') {
            throw new RuntimeException('微信登录配置未完成');
        }

        $query = http_build_query([
            'appid'      => $appid,
            'secret'     => $secret,
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 5,
                'header'  => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents('https://api.weixin.qq.com/sns/jscode2session?' . $query, false, $context);
        $payload = is_string($body) ? json_decode($body, true) : null;

        if (!is_array($payload) || !empty($payload['errcode']) || empty($payload['openid'])) {
            throw new BusinessException('微信登录失败，请稍后重试', 50300, 503);
        }

        return [
            'openid'  => (string) $payload['openid'],
            'unionid' => isset($payload['unionid']) ? (string) $payload['unionid'] : null,
        ];
    }
}
