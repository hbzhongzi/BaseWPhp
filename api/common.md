# 公共接口与数据库设计落地文档

> 本文是综合后台的公共平台接口落地基线，服务所有已注册的小程序。
> `api/user.md` 主要记录旧客户端路径和字段的兼容映射；`api/upload.md` 记录当前图片上传实现及旧路径差异。如果字段、状态码或路径与本文冲突，以本文和后端实际实现为准。
> 本文描述的是开发目标和数据契约。当前仓库已落地公共路由、统一响应、项目上下文、Token 认证、微信登录适配、用户/文件服务和版本化 migration；真实微信密钥、数据库初始化、内容安全和生产安全策略仍需在部署环境完成。

## 1. 适用范围

公共接口只提供跨小程序都需要、且语义稳定的能力：项目健康检查、启动配置、微信登录、令牌管理、当前用户、文件上传和文件元数据。商品、订单、内容、营销等功能必须放到对应的 `app/mini/{programKey}/` 业务域，不能直接塞入公共接口。

接口根路径为：

```text
/api/{programKey}/v1
```

其中 `{programKey}` 必须来自 `config/mini_programs.php` 的注册白名单。客户端不能通过请求体中的任意 `appid`、`program_id` 或 `user_id` 切换项目上下文。

## 2. 统一请求与响应

### 2.1 请求头

```text
Authorization: Bearer <access_token>       # 登录后接口必填
X-Request-Id: <uuid>                       # 可选；服务端未收到时生成
Content-Type: application/json             # JSON 请求
X-Mnp-Appid: <微信 AppID>                  # 兼容旧客户端，可选，不作为唯一身份依据
```

上传接口使用 `multipart/form-data`，仍需携带 `Authorization`。

旧前端发送的 `token` 请求头可以在过渡期由兼容层读取，但新代码统一使用 `Authorization: Bearer`。不能把任意存在的 Token 当作认证成功，必须校验签名/哈希、有效期、撤销状态和所属 `program_id`。

### 2.2 成功响应

```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "timestamp": 1730000000,
  "request_id": "5f0c2f9b-7d70-4fc9-9e7a-6c6cbf9fb8b1"
}
```

- `code=0` 表示业务成功。
- `message` 面向客户端，不放异常堆栈、SQL 或第三方密钥。
- `data` 没有业务数据时返回 `null`，不要在不同接口间混用空对象和空数组。
- `timestamp` 为 Unix 秒级时间戳。
- `request_id` 用于日志关联；服务端应在响应、日志和异步任务上下文中保持一致。

### 2.3 失败响应

```json
{
  "code": 40101,
  "message": "登录凭证无效或已过期",
  "data": null,
  "timestamp": 1730000000,
  "request_id": "5f0c2f9b-7d70-4fc9-9e7a-6c6cbf9fb8b1"
}
```

| HTTP 状态 | `code` | 含义 |
| ---: | ---: | --- |
| 400 | `40000` | 请求格式错误 |
| 401 | `40100` | 未提供登录凭证 |
| 401 | `40101` | 凭证无效、过期或已撤销 |
| 403 | `40300` | 无权访问资源 |
| 404 | `40400` | 资源不存在 |
| 409 | `40900` | 资源冲突/重复提交 |
| 422 | `42200` | 参数校验失败 |
| 429 | `42900` | 请求过于频繁 |
| 500 | `50000` | 服务内部错误 |
| 503 | `50300` | 外部依赖暂不可用 |
| 404 | `40401` | 小程序未注册或已停用 |

HTTP 状态表达协议层结果，业务 `code` 表达客户端可处理的错误分类。所有异常由统一异常处理器转换，生产环境不得返回 ThinkPHP 调试页。

## 3. 公共接口清单

| 方法 | 路径 | 登录 | 说明 |
| --- | --- | --- | --- |
| `GET` | `/api/{programKey}/v1/health` | 否 | 项目服务存活检查 |
| `GET` | `/api/{programKey}/v1/app/bootstrap` | 否 | 小程序启动配置 |
| `POST` | `/api/{programKey}/v1/auth/wechat-login` | 否 | 微信 `code` 登录/注册 |
| `POST` | `/api/{programKey}/v1/auth/refresh` | 否* | 刷新访问令牌 |
| `POST` | `/api/{programKey}/v1/auth/logout` | 是 | 撤销当前会话 |
| `GET` | `/api/{programKey}/v1/users/me` | 是 | 获取当前用户 |
| `PATCH` | `/api/{programKey}/v1/users/me` | 是 | 修改允许修改的个人资料 |
| `POST` | `/api/{programKey}/v1/files/images` | 是 | 图片中转上传 |
| `GET` | `/api/{programKey}/v1/files/{file_id}` | 是 | 获取文件元数据 |
| `DELETE` | `/api/{programKey}/v1/files/{file_id}` | 是 | 删除本人可管理的文件 |

`refresh` 使用短期 access token 之外的 refresh token 或服务端会话凭证，具体实现必须先完成令牌方案；不能用过期 access token 直接换新 token。

## 4. 接口详细说明

### 4.1 `GET /health`

用于负载均衡存活检查，不连接微信和数据库。依赖就绪检查应另设内部接口，不能让公网健康检查泄露数据库信息。

成功 `data`：

```json
{
  "status": "ok",
  "service": "wechat-mini-program-api",
  "program_key": "sample"
}
```

### 4.2 `GET /app/bootstrap`

返回当前项目公开配置。配置必须由服务端按可信 `programKey` 加载，禁止返回 AppSecret、数据库地址、内部权限和未发布配置。

成功 `data`：

```json
{
  "program_key": "sample",
  "name": "示例小程序",
  "api_version": "v1",
  "upload": {
    "image_max_bytes": 10485760,
    "allowed_extensions": ["jpg", "jpeg", "png", "webp"]
  },
  "features": {
    "phone_bind": false
  }
}
```

### 4.3 `POST /auth/wechat-login`

使用微信小程序 `wx.login` / `uni.login` 返回的临时 `code` 登录。服务端根据路由项目配置调用微信 `jscode2session`，并将 `(program_id, openid)` 映射到项目用户。

请求体：

```json
{
  "code": "微信临时登录 code",
  "device_id": "可选，客户端生成的设备标识",
  "nickname": "可选，首次登录资料",
  "avatar_url": "可选，首次登录头像"
}
```

规则：

- `code` 必填、一次性使用，服务端不记录明文 `session_key`。
- 昵称和头像只能作为展示资料，不能作为身份凭据；资料更新必须经过字段白名单和长度校验。
- 登录接口必须限流、记录脱敏登录日志，并处理微信超时、重复 code 和账号已停用。

成功 `data`：

```json
{
  "user": {
    "id": 10001,
    "nickname": "昵称",
    "avatar_url": "https://example.invalid/avatar.png",
    "is_new_user": true
  },
  "token": {
    "access_token": "仅示例，生产返回随机不可预测值",
    "token_type": "Bearer",
    "expires_in": 7200,
    "refresh_token": "仅在选定 refresh 方案后返回"
  }
}
```

### 4.4 `POST /auth/refresh`

请求体：

```json
{
  "refresh_token": "刷新凭证"
}
```

刷新成功后旧 refresh token 默认轮换并立即失效；检测到同一 token 重放时，应撤销该 token 家族并记录安全事件。返回结构与登录接口的 `token` 相同。

### 4.5 `POST /auth/logout`

请求头携带当前 access token。服务端撤销当前会话/令牌；接口应幂等，重复调用仍返回成功。

成功 `data`：`null`。

### 4.6 `GET /users/me`

返回当前项目下的用户资料，不允许通过 query 参数传入其他用户 ID。

成功 `data`：

```json
{
  "id": 10001,
  "nickname": "昵称",
  "avatar_url": "https://example.invalid/avatar.png",
  "mobile_masked": "138****0000",
  "is_phone_bound": false,
  "created_at": "2026-10-30T12:00:00+08:00"
}
```

手机号、openid、UnionID 等敏感字段按最小化原则返回；管理端和小程序端不能复用同一套字段权限。

### 4.7 `PATCH /users/me`

允许修改的字段由服务端白名单决定，示例：

```json
{
  "nickname": "新昵称",
  "avatar_url": "https://example.invalid/avatar.png"
}
```

禁止客户端提交 `id`、`program_id`、`openid`、`role`、`status`、`created_at` 等系统字段。修改结果返回与 `GET /users/me` 相同的用户结构。

### 4.8 `POST /files/images`

`multipart/form-data` 中字段 `file` 必填，当前允许 JPEG、PNG、WebP，默认最大 10 MiB；服务端校验 MIME、图片头、文件大小和像素数，不得只相信客户端文件名。图片固定保存在 `public/storage/images/{programKey}/YYYY/MM/`，数据库保存 `storage_disk=images` 和相对 `storage_key`。

成功 `data`：

```json
{
  "id": 90001,
  "type": "image",
  "original_name": "example.png",
  "size_bytes": 2048,
  "mime_type": "image/png",
  "url": null,
  "security_status": "pending",
  "created_at": "2026-10-30T12:00:00+08:00"
}
```

上传文件必须绑定 `program_id` 和上传用户。当前模板尚未接入内容安全服务，上传记录状态为 `pending` 且不返回公开 URL；仅 `security_status=passed` 时才提供公开 URL。生产接入时需补齐内容审核流程并确保仅审核通过文件进入公开展示。对象存储直传、分片上传、视频上传属于后续扩展，不应与图片中转接口混用。

### 4.9 `GET /files/{file_id}` 与 `DELETE /files/{file_id}`

文件 ID 查询和删除都必须同时校验 `program_id`、所有者或管理权限。删除采用软删除或状态变更，异步清理对象存储；不能仅根据可猜测的文件 ID 直接删除文件。

## 5. 兼容现有前端草案

| 现有草案接口 | 公共接口正式路径 | 兼容处理 |
| --- | --- | --- |
| `POST /login/mnpLogin` | `POST /api/{programKey}/v1/auth/wechat-login` | 当前 `route/app.php` 未注册旧路径；需要兼容时单独增加适配路由并设置下线日期 |
| `GET /user/center` | `GET /api/{programKey}/v1/users/me` | 当前 `route/app.php` 未注册旧路径；旧字段应通过 DTO 映射，不污染新模型 |
| `POST /upload/image` | `POST /api/{programKey}/v1/files/images` | 当前只提供版本化图片接口；旧路径需单独适配并复用同一安全检查 |
| `token: xxx` | `Authorization: Bearer xxx` | `Authenticate` 保留旧头过渡兼容，服务端内部统一为 access token |
| `code=1,msg` 成功 | `code=0,message` 成功 | 客户端适配层转换，禁止新接口继续使用两套成功码 |

兼容路由应设置下线日期、监控调用量和迁移负责人；不能无限期保留无版本旧接口。

## 6. 公共数据库设计

以下为 MySQL 8 / `utf8mb4` 的逻辑设计。实际表名应自动拼接 `DB_PREFIX`；所有时间使用 `DATETIME`，所有项目相关表必须带 `program_id`。金额字段不使用浮点数。

### 6.1 `sys_mini_program` 小程序注册表

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 内部项目 ID |
| `program_key` | VARCHAR(64) | UNIQUE, NOT NULL | URL/代码中的稳定标识 |
| `name` | VARCHAR(128) | NOT NULL | 展示名称 |
| `appid` | VARCHAR(64) | UNIQUE, NOT NULL | 微信 AppID，不存 AppSecret |
| `status` | TINYINT | DEFAULT 1 | `1` 启用，`0` 停用 |
| `config_ref` | VARCHAR(128) | NULL | 密钥管理系统引用 |
| `created_at` | DATETIME | NOT NULL | 创建时间 |
| `updated_at` | DATETIME | NOT NULL | 更新时间 |

索引：`uk_program_key(program_key)`、`uk_appid(appid)`、`idx_status(status)`。

### 6.2 `sys_user` 平台用户主表

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 平台用户 ID |
| `nickname` | VARCHAR(64) | NOT NULL | 展示昵称 |
| `avatar_url` | VARCHAR(512) | NULL | 头像地址 |
| `mobile_ciphertext` | VARBINARY(512) | NULL | 加密手机号，禁止明文存储 |
| `mobile_hash` | CHAR(64) | NULL | 查询用不可逆哈希 |
| `status` | TINYINT | DEFAULT 1 | `1` 正常，`0` 禁用 |
| `created_at` | DATETIME | NOT NULL | 创建时间 |
| `updated_at` | DATETIME | NOT NULL | 更新时间 |

索引：`uk_mobile_hash(mobile_hash)`（允许 NULL）。平台用户不直接保存某个小程序的 openid。

### 6.3 `sys_mini_program_user` 项目用户映射

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 映射 ID |
| `program_id` | BIGINT UNSIGNED | FK, NOT NULL | 所属小程序 |
| `user_id` | BIGINT UNSIGNED | FK, NOT NULL | 平台用户 |
| `openid_ciphertext` | VARBINARY(512) | NOT NULL | 加密 openid |
| `openid_hash` | CHAR(64) | NOT NULL | 查询用哈希 |
| `unionid_ciphertext` | VARBINARY(512) | NULL | 有权限时保存 |
| `last_login_at` | DATETIME | NULL | 最近登录时间 |
| `created_at` | DATETIME | NOT NULL | 首次绑定时间 |
| `updated_at` | DATETIME | NOT NULL | 更新时间 |

索引：`uk_program_openid(program_id, openid_hash)`、`uk_program_user(program_id, user_id)`、`idx_user_id(user_id)`。这张表是小程序用户隔离的核心，所有用户查询必须带 `program_id`。

### 6.4 `sys_user_token` 用户令牌表

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 会话 ID |
| `program_id` | BIGINT UNSIGNED | NOT NULL | 所属小程序 |
| `user_id` | BIGINT UNSIGNED | NOT NULL | 用户 ID |
| `token_hash` | CHAR(64) | UNIQUE, NOT NULL | 只存 token 哈希，不存明文 |
| `token_family` | CHAR(36) | NOT NULL | refresh token 轮换家族 |
| `token_type` | VARCHAR(16) | NOT NULL | `access` / `refresh` |
| `device_id` | VARCHAR(128) | NULL | 设备标识 |
| `expires_at` | DATETIME | NOT NULL | 过期时间 |
| `revoked_at` | DATETIME | NULL | 撤销时间 |
| `created_at` | DATETIME | NOT NULL | 签发时间 |

索引：`uk_token_hash(token_hash)`、`idx_program_user(program_id, user_id)`、`idx_family(token_family)`、`idx_expire(expires_at)`。清理任务定期删除已过期记录，但保留安全审计所需的最小日志。

### 6.5 `sys_file` 公共文件元数据表

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 文件 ID |
| `program_id` | BIGINT UNSIGNED | NOT NULL | 所属小程序 |
| `user_id` | BIGINT UNSIGNED | NOT NULL | 上传用户 |
| `storage_disk` | VARCHAR(32) | NOT NULL | 文件存储盘 |
| `storage_key` | VARCHAR(512) | UNIQUE, NOT NULL | 对象存储键，不直接信任客户端路径 |
| `type` | VARCHAR(16) | NOT NULL | `image` / `video` / `file` |
| `original_name` | VARCHAR(255) | NOT NULL | 原始文件名，展示用 |
| `mime_type` | VARCHAR(128) | NOT NULL | 服务端检测结果 |
| `size_bytes` | BIGINT UNSIGNED | NOT NULL | 文件大小 |
| `security_status` | VARCHAR(16) | NOT NULL | `pending` / `passed` / `rejected` |
| `status` | TINYINT | DEFAULT 1 | `1` 有效，`0` 已删除 |
| `created_at` | DATETIME | NOT NULL | 上传时间 |
| `deleted_at` | DATETIME | NULL | 软删除时间 |

索引：`uk_storage_key(storage_key)`、`idx_program_user(program_id, user_id)`、`idx_security(security_status)`。

### 6.6 `sys_login_log` 登录审计表

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | 日志 ID |
| `program_id` | BIGINT UNSIGNED | NOT NULL | 所属小程序 |
| `user_id` | BIGINT UNSIGNED | NULL | 登录成功后写入 |
| `request_id` | VARCHAR(128) | NOT NULL | 请求关联 ID |
| `login_type` | VARCHAR(24) | NOT NULL | `wechat_code` |
| `result` | VARCHAR(16) | NOT NULL | `success` / `failed` |
| `failure_code` | VARCHAR(32) | NULL | 内部失败分类 |
| `ip_hash` | CHAR(64) | NULL | 脱敏后的 IP |
| `user_agent` | VARCHAR(512) | NULL | 脱敏后设备信息 |
| `created_at` | DATETIME | NOT NULL | 发生时间 |

该表不得记录 `code`、`session_key`、AppSecret、明文 token 或完整个人敏感资料。按合规要求设置保留期限。

## 7. 数据库约束与落地顺序

1. 先建立 `sys_mini_program`，注册项目并配置密钥引用；密钥实际值放部署环境。
2. 建立 `sys_user` 与 `sys_mini_program_user`，完成 `(program_id, openid_hash)` 唯一约束。
3. 建立 `sys_user_token`，确定 access/refresh 生命周期、轮换和撤销策略后再开放刷新接口。
4. 建立 `sys_file`，接入文件类型检查、内容安全检查和对象存储清理任务。
5. 建立 `sys_login_log`，接入请求 ID、限流和脱敏日志。
6. 为每次表结构变更提供 migration、回滚说明和跨版本兼容策略；当前仓库提供 `database/migrations/` 与 `database/migrate.php`，公共表字段注释由 `202610300002_add_public_column_comments` 维护，新增变更不得修改已执行迁移。

## 8. 实现拆分建议

```text
app/common/
├── controller/v1/       Bootstrap、Auth、User、File
├── service/             WechatLoginService、TokenService、FileService
├── model/               MiniProgram、User、MiniProgramUser、UserToken、File
├── validate/            Login、UserProfile、Upload
├── middleware/          ResolveMiniProgram、Authenticate
└── response/            ApiResponse
```

每个小程序只负责自己的业务目录；公共登录服务接收可信 `program_id`，不能接收客户端任意传入的项目 ID。控制器不直接调用微信 HTTP、对象存储或数据库查询，业务流程放 Service，持久化放 Model/Repository。

## 9. 联调验收清单

- [ ] 未注册或停用的 `programKey` 返回 `40401`。
- [ ] 无 token、过期 token、已撤销 token、跨项目 token 都不能访问登录后接口。
- [ ] 两个小程序可以使用相同 openid 字符串而不发生数据串读。
- [ ] 登录重复提交、refresh token 重放、上传重复回调均有幂等处理。
- [ ] 上传文件按服务端检测的 MIME/签名校验，未通过安全检查的文件不可公开访问。
- [ ] 所有错误响应不包含 SQL、堆栈、密钥、session_key 或明文 token。
- [ ] 公共接口、旧兼容接口和业务接口的调用量可分别监控，旧接口有明确下线日期。
- [ ] migration 可在空库和已有数据环境重复验证，备份恢复演练通过。

