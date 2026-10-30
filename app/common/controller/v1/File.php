<?php
declare(strict_types=1);

namespace app\common\controller\v1;

use app\BaseController;
use app\common\service\FileService;
use think\response\Json;

final class File extends BaseController
{
    public function image(): Json
    {
        return $this->success(app()->make(FileService::class)->uploadImage($this->request));
    }

    public function show(int $fileId): Json
    {
        return $this->success(app()->make(FileService::class)->find($this->request, $fileId));
    }

    public function delete(int $fileId): Json
    {
        app()->make(FileService::class)->delete($this->request, $fileId);
        return $this->success(null);
    }
}
