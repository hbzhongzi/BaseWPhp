# 后端接口文档

本目录是本项目综合小程序后台的接口文档入口。公共接口已经按 ThinkPHP 路由、控制器、Service 和数据库设计落地；业务小程序接口应继续在各自项目目录和路由文件中维护。

文档优先级如下：

1. 服务端实际路由、控制器、校验器和 Service。
2. [公共接口与数据库设计落地文档](./common.md)。
3. 本目录中的用户、上传兼容说明。

`api/user.md` 和早期的前端调用说明只用于兼容联调，若与当前服务端实现冲突，以服务端实现和 `common.md` 为准。

## 基础配置

- 请求根地址：由部署环境的 `APP_URL` 和客户端配置提供。
- API 根路径：`/api/{programKey}/v1`，其中 `programKey` 必须在 `config/mini_programs.php` 注册且处于启用状态。
- 当前公共模块：健康检查、启动配置、微信登录、令牌、当前用户和图片文件。
- 小程序商品、订单、内容、营销等功能必须放入 `app/mini/{programKey}/`，不能写入公共 Controller。

## 请求协议

普通 JSON 请求返回统一结构：

```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "timestamp": 1793351360,
  "request_id": "5f0c2f9b7d704fc99e7a6c6cbf9fb8b1"
}
```

约定：

- `code=0` 表示业务成功；失败时使用稳定的四位业务码，例如 `40100`、`40400`、`42200`。
- HTTP 状态码表示协议层结果，不能只根据 HTTP 200 判断业务成功。
- `request_id` 与响应头 `X-Request-Id` 一致，用于关联 `runtime/program_logs/{programKey}/YYYY-MM-DD.log`。
- 失败响应不返回 SQL、堆栈、文件路径、Token、密钥或原始异常消息。

请求头：

```text
Authorization: Bearer <access_token>
X-Request-Id: <可选请求 ID>
Content-Type: application/json
X-Mnp-Appid: <兼容旧客户端，可选>
```

文件上传使用 `multipart/form-data`。旧客户端的 `token` 请求头可以在过渡期兼容，但新接口统一使用 `Authorization`。

## 接口目录

- [公共接口与数据库设计落地文档](./common.md)
- [用户接口](./user.md)
- [图片上传接口](./upload.md)
- [“今天要做什么饭”后端接口落地说明](./Teat/今天要做什么饭-后端接口落地说明.md)

## 当前已落地能力

| 能力 | 当前约定 |
| --- | --- |
| 项目隔离 | `/api/{programKey}/v1` 路由 + `ResolveMiniProgram`，数据库查询必须带 `program_id` |
| 认证 | 登录后路由使用 `Authenticate` 校验 access token；不能只检查是否携带 Token |
| 文件存储 | 图片写入 `public/storage/images/{programKey}/YYYY/MM/`，服务端生成随机文件名 |
| 内容安全状态 | 新上传文件为 `pending`，未接入审核前不返回公开 URL；仅 `passed` 可公开 |
| 请求日志 | JSON Lines 写入 `runtime/program_logs/{programKey}/YYYY-MM-DD.log`，按 `request_id` 排查 |
| 日志脱敏 | 不记录请求体、响应体、Token、密钥、openid、手机号和原始异常消息 |

## 联调与验收

```bash
php think route:list
curl -i https://api.example.com/api/sample/v1/health
curl -i https://api.example.com/api/sample/v1/users/me
```

验收至少包括：健康检查返回 `200/code=0`；未认证接口返回 `401/code=40100`；响应包含 `X-Request-Id` 和 `request_id`；对应项目日志文件存在请求记录；跨小程序访问不能读取其他项目的数据。

## 尚未内置的能力

- RBAC、支付、内容安全审核、对象存储直传、视频上传和完整的业务领域接口需要按项目单独实现。
- 微信 AppID/AppSecret、数据库连接、生产 HTTPS、日志保留和告警策略必须由部署环境配置。
- 旧接口适配必须设置负责人、监控和下线日期，不能无限期保留无版本接口。
