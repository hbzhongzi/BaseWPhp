<?php
declare(strict_types=1);

namespace app\common\model;

final class LoginLog extends BaseModel
{
    protected $name = 'sys_login_log';
    protected $autoWriteTimestamp = false;
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'user_id'    => 'integer',
    ];
}
