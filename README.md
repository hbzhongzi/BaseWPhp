# 微信小程序后台模板

本项目基于 ThinkPHP 8，定位为服务多个微信小程序的综合后台 API 模板。采用“共享平台能力 + 小程序独立业务域”：登录/基础设施可共享，每个小程序的业务控制器、服务、模型、路由独立维护。当前已落地公共接口、项目上下文、Token 认证和数据库初始化脚本；微信密钥、完整授权策略、迁移流程和各小程序业务仍需按项目配置与开发。

## 环境要求

- PHP 8.0 或更高版本，并启用项目所需扩展（至少 PDO 及对应数据库驱动）
- Composer 2
- MySQL 5.7+ 或兼容数据库

## 本地启动

```bash
composer install
cp .example.env .env
```

在 `.env` 中填写数据库连接，再将 Web 服务器站点根目录指向项目的 `public/`。本地也可用 ThinkPHP 内置服务器：

```bash
php think run
```

初始化或升级数据库：

```bash
php database/migrate.php status
php database/migrate.php up
```

首次部署、环境变量、数据库迁移和 Nginx 配置见 [doce/项目初始化部署文档.md](doce/项目初始化部署文档.md)。

示例租户健康检查：`GET /api/sample/v1/health`。预期返回统一 JSON 格式。生产环境请关闭调试、禁止 Web 访问项目根目录，并通过 HTTPS 提供服务。

## API 约定

- 路径按 `/api/{programKey}/v1/...` 版本化；项目在 `config/mini_programs.php` 注册，业务路由和代码位于对应独立目录。
- 控制器只处理 HTTP 输入输出；项目业务流程放到 `app/mini/{programKey}/service/`，稳定共享能力放 `app/common/`。
- API 响应使用 `code`、`message`、`data`、`timestamp` 四个字段。`code=0` 表示业务成功；HTTP 状态码仍用于表达协议层状态。
- 所有客户端输入都必须验证；不要信任小程序传来的用户 ID、角色或价格。
- 不要把微信 `AppSecret`、数据库密码、签名密钥提交到版本库。微信登录应由服务端调用 `jscode2session`，并实现安全的会话/令牌签发、过期、撤销和权限校验。

## 目录结构

目录说明见 [doce/目录结构说明.md](doce/目录结构说明.md)，初始化部署见 [doce/项目初始化部署文档.md](doce/项目初始化部署文档.md)，接口、隔离、认证、测试和发布要求见 [docs/项目开发规范.md](docs/项目开发规范.md)。

公共接口、数据库设计、兼容迁移和联调验收见 [api/common.md](api/common.md)；接口文档入口见 [api/README.md](api/README.md)。`api/upload.md` 已同步当前图片固定目录、审核状态和公开 URL 规则，`api/user.md` 保留旧客户端路径和字段的兼容说明。

## 当前边界

模板包含项目注册、路由隔离、Token 认证、微信登录适配、图片固定目录存储和按项目分区日志，但这不等于完整的业务授权系统。本模板不是完整的微信开放平台 SDK，也没有预置 RBAC、支付或内容安全审核服务。接入真实项目时，必须按开发规范补充认证授权、审核策略、日志保留和数据隔离，不能只检查是否携带任意 Token。

