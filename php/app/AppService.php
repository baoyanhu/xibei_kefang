<?php
declare(strict_types=1);

namespace app;

use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register(): void
    {
        // 服务注册：容器绑定 AliyunLogger
        $this->app->bind('lib\AliyunLogger', \lib\AliyunLogger::class);
    }

    public function boot(): void
    {
        // 服务启动
    }
}
