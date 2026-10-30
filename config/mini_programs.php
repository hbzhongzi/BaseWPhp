<?php
declare(strict_types=1);

/**
 * 小程序/业务租户注册表。只放非敏感元数据；微信密钥必须来自环境变量或密钥管理服务。
 */
return [
    'programs' => [
        // 示例项目。复制此结构注册真实小程序，并为其创建独立业务目录和路由文件。
        'sample' => [
            'name'          => '示例小程序',
            'enabled'       => true,
            'appid_env'     => 'WECHAT_SAMPLE_APPID',
            'secret_env'    => 'WECHAT_SAMPLE_SECRET',
            'features'      => [],
        ],
    ],
    'upload' => [
        'image_max_bytes'    => 10485760,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    ],
];
