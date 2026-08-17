<?php
// config/log.php — 权威模板（键名小写下划线 + env() 小写点分键 + TP8 原生 channels 兼容）
return [
    // 日志记录方式，内置 file socket 支持扩展
    'type'          => env('log.type', 'file'),
    // 阿里云日志服务的域名
    'end_point'     => env('aliyunlog.end_point'),
    // 阿里云访问密钥AccessKey ID
    'access_key_id' => env('aliyunlog.access_key_id'),
    // 阿里云访问密钥AccessKey Secret
    'access_key'    => env('aliyunlog.access_key'),
    // 阿里云日志项目 project
    'project'       => env('aliyunlog.project'),
    // TP日志的阿里云日志仓库
    'logstore'      => env('aliyunlog.logstore', 'inner_notp'),
    // 日志记录级别
    'level'         => ['error'],
    // 日志版本（写入 message.version 字段，env 驱动）
    'log_version'   => env('log.log_version', '1.0.0'),

    // 默认日志记录通道（保留 TP8 原生 channel 供框架内部解析）
    'default'       => env('log.driver', 'file'),
    // 日志通道列表
    'channels'      => [
        'file' => [
            'type'           => 'file',
            'path'           => '',
            'level'          => [],
            'format'         => '[%s][%s] %s',
            'single'         => false,
            'max_files'      => 0,
            'file_size'      => 2097152,
            'close'          => false,
        ],
    ],
];
