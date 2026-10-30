<?php
declare(strict_types=1);

namespace app\common\model;

final class MiniProgram extends BaseModel
{
    protected $name = 'sys_mini_program';
    protected $type = ['id' => 'integer', 'status' => 'integer'];
}
