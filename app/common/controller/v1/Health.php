<?php
declare(strict_types=1);

namespace app\common\controller\v1;

use app\BaseController;
use app\common\support\MiniProgramContext;
use think\response\Json;

final class Health extends BaseController
{
    public function index(): Json
    {
        return $this->success([
            'status'      => 'ok',
            'service'     => 'wechat-mini-program-api',
            'program_key' => MiniProgramContext::key($this->request),
        ]);
    }
}
