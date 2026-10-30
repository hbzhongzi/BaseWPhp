-- 公共表迁移：小程序注册信息。
-- program_key 是 URL 和代码中的稳定项目标识，appid 仅保存公开 AppID。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_mini_program` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '小程序内部主键',
  `program_key` VARCHAR(64) NOT NULL COMMENT '小程序稳定标识，用于 API 路径和代码命名',
  `name` VARCHAR(128) NOT NULL COMMENT '小程序显示名称',
  `appid` VARCHAR(64) NOT NULL COMMENT '微信小程序 AppID，不是 AppSecret',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1 启用，0 停用',
  `config_ref` VARCHAR(128) NULL COMMENT '密钥管理系统配置引用',
  `created_at` DATETIME NOT NULL COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_key` (`program_key`),
  UNIQUE KEY `uk_appid` (`appid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 平台用户主表：不直接绑定某个小程序的 openid。
-- 手机号使用密文和哈希字段，明文手机号不落库。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '平台用户主键',
  `nickname` VARCHAR(64) NOT NULL COMMENT '用户展示昵称',
  `avatar_url` VARCHAR(512) NULL COMMENT '用户头像地址',
  `mobile_ciphertext` VARBINARY(512) NULL COMMENT '加密手机号密文',
  `mobile_hash` CHAR(64) NULL COMMENT '手机号查询哈希，不可逆',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1 正常，0 禁用',
  `created_at` DATETIME NOT NULL COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile_hash` (`mobile_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 小程序用户映射表：通过 program_id + openid_hash 实现项目隔离。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_mini_program_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '项目用户映射主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '平台用户 ID',
  `openid_ciphertext` VARBINARY(512) NOT NULL COMMENT '加密后的微信 OpenID',
  `openid_hash` CHAR(64) NOT NULL COMMENT 'OpenID 查询哈希，不可逆',
  `unionid_ciphertext` VARBINARY(512) NULL COMMENT '加密后的微信 UnionID',
  `last_login_at` DATETIME NULL COMMENT '最近登录时间',
  `created_at` DATETIME NOT NULL COMMENT '首次绑定时间',
  `updated_at` DATETIME NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_openid` (`program_id`, `openid_hash`),
  UNIQUE KEY `uk_program_user` (`program_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_program_id` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 登录令牌表：只保存 token_hash，不保存 access token/refresh token 明文。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_user_token` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '令牌记录主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户 ID',
  `token_hash` CHAR(64) NOT NULL COMMENT '令牌哈希，不保存明文令牌',
  `token_family` CHAR(32) NOT NULL COMMENT '刷新令牌轮换家族标识',
  `token_type` VARCHAR(16) NOT NULL COMMENT '令牌类型：access 或 refresh',
  `device_id` VARCHAR(128) NULL COMMENT '客户端设备标识',
  `expires_at` DATETIME NOT NULL COMMENT '令牌过期时间',
  `revoked_at` DATETIME NULL COMMENT '令牌撤销时间',
  `created_at` DATETIME NOT NULL COMMENT '签发时间',
  `updated_at` DATETIME NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_program_user` (`program_id`, `user_id`),
  KEY `idx_family` (`token_family`),
  KEY `idx_expire` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文件元数据表：保存对象存储键和安全检测状态，不直接信任客户端路径。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_file` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '文件记录主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '上传用户 ID',
  `storage_disk` VARCHAR(32) NOT NULL COMMENT '存储磁盘标识',
  `storage_key` VARCHAR(512) NOT NULL COMMENT '对象存储键或文件相对路径',
  `type` VARCHAR(16) NOT NULL COMMENT '文件类型：image、video 或 file',
  `original_name` VARCHAR(255) NOT NULL COMMENT '客户端原始文件名',
  `mime_type` VARCHAR(128) NOT NULL COMMENT '服务端检测出的 MIME 类型',
  `size_bytes` BIGINT UNSIGNED NOT NULL COMMENT '文件大小，单位字节',
  `security_status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT '内容安全状态：pending、passed、rejected',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1 有效，0 已删除',
  `created_at` DATETIME NOT NULL COMMENT '上传时间',
  `updated_at` DATETIME NOT NULL COMMENT '最后更新时间',
  `deleted_at` DATETIME NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_storage_key` (`storage_key`),
  KEY `idx_program_user` (`program_id`, `user_id`),
  KEY `idx_security` (`security_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 登录审计表：只记录脱敏信息，不记录 code、session_key、AppSecret 或明文 token。
CREATE TABLE IF NOT EXISTS `{{prefix}}sys_login_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '登录日志主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NULL COMMENT '登录成功后的用户 ID',
  `request_id` VARCHAR(128) NOT NULL COMMENT '请求链路关联 ID',
  `login_type` VARCHAR(24) NOT NULL COMMENT '登录方式，例如 wechat_code',
  `result` VARCHAR(16) NOT NULL COMMENT '结果：success 或 failed',
  `failure_code` VARCHAR(32) NULL COMMENT '脱敏后的失败分类',
  `ip_hash` CHAR(64) NULL COMMENT '客户端 IP 哈希',
  `user_agent` VARCHAR(512) NULL COMMENT '脱敏后的客户端信息',
  `created_at` DATETIME NOT NULL COMMENT '日志发生时间',
  PRIMARY KEY (`id`),
  KEY `idx_program_created` (`program_id`, `created_at`),
  KEY `idx_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
