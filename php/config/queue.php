<?php
return [
    // 默认连接器：redis 推荐；sync 同步（仅调试）
    'default'      => env('queue.driver', 'redis'),
    'connections'  => [
        'sync'  => ['type' => 'sync'],
        'redis' => [
            'type'       => 'redis',
            // 连接参数读 [redis] 段（与 cache/session 共享），DB 号读 [queue] 段
            'host'       => env('redis.host', '127.0.0.1'),
            'port'       => (int) env('redis.port', 6379),
            'password'   => env('redis.password', ''),
            'select'     => (int) env('queue.select', 2),
            'expire'     => 60,
            'persistent' => false,
            'prefix'     => env('queue.prefix', 'store:queue:'),
        ],
    ],
    // 失败任务处理（项目自定义：BaseJob + queue_error 表管理）
    'failed' => ['type' => 'none'],
];
