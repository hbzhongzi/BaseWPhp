# 图片上传接口

本文描述当前 PHP 模板实际提供的公共图片上传接口。早期前端草案中的 `/upload/image`、视频/普通文件上传及 `security_status` 数字枚举不是本接口契约；新接口以服务端代码和 `api/common.md` 为准。

## `POST /api/{programKey}/v1/files/images`

- 请求类型：`multipart/form-data`
- 登录：必需，使用 `Authorization: Bearer <access_token>`；旧客户端的 `token` 请求头仅作过渡兼容。
- 小程序身份：由 URL 中已注册的 `{programKey}` 确定。
- 文件字段：`file`，必填。
- 支持格式：JPEG、PNG、WebP；单文件默认上限 10 MiB，可通过 `config/mini_programs.php` 中 `upload.image_max_bytes` 调整。
- 服务端检查文件 MIME、图片头、文件大小和像素总数（最多 50,000,000 像素）；客户端文件名不用于生成存储路径。

示例（curl）：

```bash
curl -X POST 'https://api.example.com/api/sample/v1/files/images' \
  -H 'Authorization: Bearer <access_token>' \
  -H 'X-Request-Id: upload-image-001' \
  -F 'file=@./example.png'
```

### 存储规则

图片固定存入 `public/storage/images/{programKey}/YYYY/MM/`，磁盘标识为 `images`，数据库只保存相对 `storage_key`。响应中的 URL 前缀默认为 `/storage/images`；同一个服务器上的图片也会按小程序标识分目录，禁止客户端提交路径、磁盘名或目标文件名。

这是公开静态目录。部署时必须禁止该目录内的 PHP/脚本执行，并确保 Web 进程只有必要的读写权限。模板尚未接入微信/第三方内容安全服务，所以新文件的 `security_status` 为 `pending`，此时接口返回的 `url` 为 `null`；只有状态变为 `passed` 后才返回公开 URL。生产环境需接入审核流程，禁止直接向用户或业务页面公开待审核/拒绝文件。

### 成功响应

统一外层为 `{ code, message, data, timestamp, request_id }`，其中 `code=0` 表示成功：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 90001,
    "type": "image",
    "original_name": "example.png",
    "size_bytes": 2048,
    "mime_type": "image/png",
    "url": null,
    "security_status": "pending",
    "created_at": "2026-10-30 12:00:00"
  },
  "timestamp": 1793332800,
  "request_id": "upload-image-001"
}
```

失败响应使用统一错误结构；格式/大小/图片内容校验失败返回 HTTP `422`，无登录凭证返回 `401`。客户端应按 `security_status` 的字符串枚举 `pending` / `passed` / `rejected` 处理，不使用旧草案的数字值。

## 文件元数据接口

- `GET /api/{programKey}/v1/files/{file_id}`：仅能读取当前小程序内有效文件。
- `DELETE /api/{programKey}/v1/files/{file_id}`：仅上传者可删除；当前行为为软删除，不立即物理删除图片。

完整表字段、统一响应和多小程序隔离要求见 [公共接口与数据库设计](./common.md)。
