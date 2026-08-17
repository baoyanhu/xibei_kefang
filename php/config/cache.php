<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动（本地缺 phpredis 扩展时默认 fallback 到 file，配置 redis 时走 redis）
    'default' => env('cache.driver', extension_loaded('redis') ? 'redis' : 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        'redis' => [
            'type'       => 'redis',
            'host'       => env('cache.host', '127.0.0.1'),
            'port'       => (int) env('cache.port', 6379),
            'password'   => env('cache.password', ''),
            'select'     => (int) env('cache.select', 0),
            'timeout'    => 0,
            'expire'     => 0,
            'persistent' => false,
            'prefix'     => env('cache.prefix', 'store:cache:'),
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
    ],
];
