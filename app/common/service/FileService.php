<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\BusinessException;
use app\common\model\File as FileModel;
use app\common\support\MiniProgramContext;
use think\Request;
use think\file\UploadedFile;

final class FileService
{
    private const IMAGE_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function uploadImage(Request $request): array
    {
        $program = MiniProgramContext::record($request);
        $user = $request->middleware('auth_user');
        $file = $request->file('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new BusinessException('请选择有效的图片文件', 42200, 422);
        }

        $sizeBytes = (int) $file->getSize();
        $maxBytes = (int) config('mini_programs.upload.image_max_bytes', 10485760);
        if ($sizeBytes < 1 || $sizeBytes > $maxBytes) {
            throw new BusinessException('图片不能超过 10MB', 42200, 422);
        }

        $mime = $file->getMime();
        if (!isset(self::IMAGE_MIME[$mime])) {
            throw new BusinessException('不支持的图片格式', 42200, 422);
        }

        // MIME 识别通过后再校验真实图片头，避免只靠客户端扩展名接收伪装文件。
        $imageInfo = @getimagesize($file->getPathname());
        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new BusinessException('图片文件内容无效', 42200, 422);
        }

        $maxPixels = 50000000;
        if (($imageInfo[0] * $imageInfo[1]) > $maxPixels) {
            throw new BusinessException('图片尺寸过大', 42200, 422);
        }

        $programKey = (string) $program->program_key;
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $programKey)) {
            throw new BusinessException('小程序存储标识配置无效', 50000, 500);
        }

        // 所有图片落在固定磁盘根目录下，并按小程序、年月分层；文件名不采用客户端输入。
        $relative = $programKey . '/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . self::IMAGE_MIME[$mime];
        $disk = config('filesystem.disks.images', []);
        $root = rtrim((string) ($disk['root'] ?? ''), '/\\');
        if ($root === '') {
            throw new BusinessException('图片存储目录未配置', 50000, 500);
        }

        $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, dirname($relative));
        $file->move($destination, basename($relative));
        $key = str_replace('\\', '/', $relative);
        $record = FileModel::create([
            'program_id'     => $program->id,
            'user_id'        => $user->id,
            'storage_disk'   => 'images',
            'storage_key'    => $key,
            'type'           => 'image',
            'original_name'  => method_exists($file, 'getOriginalName') ? $file->getOriginalName() : $file->getFilename(),
            'mime_type'      => $mime,
            'size_bytes'     => $sizeBytes,
            // 模板尚未接入内容安全服务，不伪报为已通过。
            'security_status'=> 'pending',
            'status'         => 1,
        ]);

        return [
            'id'              => (int) $record->id,
            'type'            => 'image',
            'original_name'   => $record->original_name,
            'size_bytes'      => (int) $record->size_bytes,
            'mime_type'       => $record->mime_type,
            'url'             => $this->publicUrl($record),
            'security_status' => $record->security_status,
            'created_at'      => $record->created_at,
        ];
    }

    public function find(Request $request, int $fileId): array
    {
        $program = MiniProgramContext::record($request);
        $record = FileModel::where('id', $fileId)
            ->where('program_id', $program->id)
            ->where('status', 1)
            ->find();

        if (!$record) {
            throw new BusinessException('文件不存在', 40400, 404);
        }

        return $this->data($record);
    }

    public function delete(Request $request, int $fileId): void
    {
        $program = MiniProgramContext::record($request);
        $user = $request->middleware('auth_user');
        $record = FileModel::where('id', $fileId)
            ->where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->find();

        if (!$record) {
            throw new BusinessException('文件不存在或无权删除', 40400, 404);
        }

        $record->save(['status' => 0, 'deleted_at' => date('Y-m-d H:i:s')]);
    }

    private function data(FileModel $record): array
    {
        return [
            'id'              => (int) $record->id,
            'type'            => $record->type,
            'original_name'   => $record->original_name,
            'size_bytes'      => (int) $record->size_bytes,
            'mime_type'       => $record->mime_type,
            'url'             => $this->publicUrl($record),
            'security_status' => $record->security_status,
            'created_at'      => $record->created_at,
        ];
    }

    private function publicUrl(FileModel $record): ?string
    {
        if ((string) $record->security_status !== 'passed') {
            return null;
        }

        $baseUrl = rtrim((string) env('APP_URL', ''), '/');
        $disk = config('filesystem.disks.' . $record->storage_disk, []);
        $urlPrefix = rtrim((string) ($disk['url'] ?? '/storage'), '/');

        return $baseUrl . $urlPrefix . '/' . ltrim((string) $record->storage_key, '/');
    }
}
