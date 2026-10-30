<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

abstract class BaseModel extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
