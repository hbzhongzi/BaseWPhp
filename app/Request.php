<?php
namespace app;

// 应用请求对象类
class Request extends \think\Request
{
    /** Provide URL configuration to ThinkPHP's URL helper in 8.1.x. */
    public function config(string $name = '', mixed $default = null): mixed
    {
        return app()->config->get('route.' . $name, $default);
    }
}
