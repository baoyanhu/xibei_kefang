<?php
declare(strict_types=1);

/**
 * 本地联调脚本：对 /admin/questionnaire/* 发起带 AdminSign 验签的真实请求
 *
 * 用法：php tests_call_questionnaire.php <detail|save>
 * 凭证走 AppkeyService 真实链路（MDC 拉取 + 缓存），签名算法与 lib/XibeiApiClient 同源。
 */

require __DIR__ . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\admin\AppkeyService;
use sdk\Mdc;

// ---------------- 1. 取真实凭证 ----------------
$appInfo = (new AppkeyService(new Mdc()))->getInfo(config('auth.auth_appid'));

// ---------------- 2. 组装验签参数（MD5 方式，与 AdminSign 的 checkSign 对齐） ----------------
function buildSignedParams(array $appInfo, array $biz): array
{
    $params = array_merge($biz, [
        'app_id'     => $appInfo['app_id'],
        'timestamp'  => time(),
        'nonce_str'  => bin2hex(random_bytes(8)),
        'sign_type'  => 'MD5',
    ]);
    // 与 XibeiApiClient::getSignContent 同款：ksort 后拼 key=value&，空值跳过
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        if ($v !== '' && !is_array($v)) {
            $pairs[] = $k . '=' . $v;
        }
    }
    $signContent = implode('&', $pairs);
    $params['sign'] = md5($signContent . '&key=' . $appInfo['app_key']);
    return $params;
}

function call(string $path, array $params): array
{
    $ch = curl_init('http://127.0.0.1:8080/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['http' => $code, 'err' => $err, 'body' => json_decode((string) $body, true) ?? (string) $body];
}

$mode = $argv[1] ?? 'detail';

if ($mode === 'detail') {
    $params = buildSignedParams($appInfo, ['merchant_id' => 1]);
    $res = call('admin/questionnaire/detail', $params);
} else {
    $biz = [
        'merchant_id'       => 1,
        'trigger_mode'      => 2,
        'delay_minutes'     => 30,
        'reward_points'     => 1,
        'points'            => 50,
        'reward_coupon'     => 1,
        'coupon_template_id' => '100426507',
    ];
    $params = buildSignedParams($appInfo, $biz);
    $res = call('admin/questionnaire/save', $params);
}

echo 'POST /admin/questionnaire/' . $mode . PHP_EOL;
echo 'HTTP ' . $res['http'] . ($res['err'] ? ' curl_err=' . $res['err'] : '') . PHP_EOL;
echo json_encode($res['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
