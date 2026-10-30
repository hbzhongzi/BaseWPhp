<?php
declare(strict_types=1);

namespace app\common\controller\v1;

use app\BaseController;
use app\common\support\MiniProgramContext;
use think\response\Json;

final class Bootstrap extends BaseController
{
    public function index(): Json
    {
        $context = MiniProgramContext::fromRequest($this->request);
        $upload = config('mini_programs.upload', []);

        return $this->success([
            'program_key' => $context['key'],
            'name'        => $context['name'] ?? $context['key'],
            'api_version' => 'v1',
            'upload'      => [
                'image_max_bytes'   => (int) ($upload['image_max_bytes'] ?? 10485760),
                'allowed_extensions' => $upload['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'],
            ],
            'features' => $context['features'] ?? [],
        ]);
    }
}
