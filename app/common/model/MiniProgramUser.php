<?php
declare(strict_types=1);

namespace app\common\model;

final class MiniProgramUser extends BaseModel
{
    protected $name = 'sys_mini_program_user';
    protected $type = ['id' => 'integer', 'program_id' => 'integer', 'user_id' => 'integer'];
}
