<?php
return [
    'auth_appid'          => env('auth.auth_appid', ''),
    'auth_key'            => env('auth.auth_key', ''),
    'private_key'         => env('auth.private_key', ''),
    // timestamp 容差秒（AdminSign 防重放，请求时间戳与服务器时间差超过此值返回 401）
    'timestamp_tolerance' => (int) env('auth.timestamp_tolerance', 300),
    // nonce_str 防重放缓存秒（AdminSign 防重放，同 nonce_str 在此秒数内重复请求返回 404）
    'nonce_ttl'           => (int) env('auth.nonce_ttl', 300),
];
