<?php
return [
    'rms_url'   => env('rms.rms_url', 'https://api.xibei.com.cn/rmsapi'),
    'mch_id'    => env('rms.mch_id', ''),
    'xcx_appid' => env('rms.xcx_appid', ''),
    'menu_id'   => env('rms.menu_id', ''),
    'token_ttl' => (int) env('rms.token_ttl', 3600),
];
