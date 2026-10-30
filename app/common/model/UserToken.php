<?php
declare(strict_types=1);

namespace app\common\model;

final class UserToken extends BaseModel
{
    protected $name = 'sys_user_token';
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'user_id'    => 'integer',
    ];
}
