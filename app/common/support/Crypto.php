<?php
declare(strict_types=1);

namespace app\common\support;

use RuntimeException;

final class Crypto
{
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function encrypt(string $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', self::key(true), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('敏感数据加密失败');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function key(bool $binary = false): string
    {
        $configured = trim((string) env('APP_KEY', ''));
        if ($configured === '') {
            throw new RuntimeException('APP_KEY 未配置');
        }

        return hash('sha256', $configured, $binary);
    }
}
