# 用户接口兼容说明

本文保留旧客户端使用的用户接口路径和字段，仅用于迁移和联调。当前后台公共接口正式路径见 [common.md](./common.md)，新客户端不要继续使用本文的无版本路径。

## 旧路径：`POST /login/mnpLogin`

小程序登录。前端通过 `uni.login({ provider: 'weixin' })` 获取 `code` 后调用。

### 请求体

```json
{
  "code": "微信登录临时 code"
}
```

### 成功数据

```json
{
  "id": 1,
  "sn": "用户编号",
  "mobile": "手机号",
  "nickname": "昵称",
  "avatar": "头像地址",
  "is_new_user": 1,
  "is_ad_user": 0,
  "token": "登录凭证"
}
```

旧客户端将 `data.token` 持久化，并使用它访问后续鉴权接口。新接口应调用：

```text
POST /api/{programKey}/v1/auth/wechat-login
Authorization: Bearer <access_token>
```

### 认证要求

- 前端显式标记为 `ignoreAuth: true`。
- 预期不要求携带已有 `token`。
- 是否要求 `X-Mnp-Appid` 或其他 AppID 头，由后端确认。

## 旧路径：`POST /login/mnpLogin`（刷新登录凭证）

前端刷新流程仍调用同一路径，发送同样的 `{ code }` 请求体，仅在客户端元数据中标记 `authRole: 'refreshToken'`。

因此旧客户端无法区分登录和刷新场景。当前正式接口为：

```text
POST /api/{programKey}/v1/auth/refresh
```

刷新接口必须使用服务端定义的 refresh token 或会话凭证，不能用过期 access token 直接换新 Token。

## 旧路径：`GET /user/center`

获取个人中心信息。

### 请求头

需要登录凭证：

```text
token: <登录凭证>
```

### 成功数据

```json
{
  "id": 1,
  "sn": "用户编号",
  "sex": 0,
  "account": "账号",
  "nickname": "昵称",
  "real_name": "真实姓名",
  "avatar": "头像地址",
  "mobile": "手机号",
  "create_time": 0,
  "has_password": false,
  "is_auth": 0,
  "is_new_user": 0
}
```

`is_auth` 在前端类型中为可选字段，其余字段按当前代码视为必返字段；真实空值和字段类型需以后端为准。当前正式接口为：

```text
GET /api/{programKey}/v1/users/me
PATCH /api/{programKey}/v1/users/me
Authorization: Bearer <access_token>
```

旧路径应设置调用量监控、迁移负责人和下线日期，不能与正式接口长期并行而无版本边界。
