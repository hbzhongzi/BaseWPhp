<?php
declare(strict_types=1);

namespace app\common\model;

final class File extends BaseModel
{
    protected $name = 'sys_file';
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'user_id'    => 'integer',
        'size_bytes' => 'integer',
        'status'     => 'integer',
    ];
}
