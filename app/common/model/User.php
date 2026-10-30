<?php
declare(strict_types=1);

namespace app\common\model;

final class User extends BaseModel
{
    protected $name = 'sys_user';
    protected $type = ['id' => 'integer', 'status' => 'integer'];
}
