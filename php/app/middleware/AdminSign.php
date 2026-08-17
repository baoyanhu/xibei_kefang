<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\admin\AppkeyService;
use lib\XibeiApiClient;
use think\facade\Cache;
use think\Request;
use think\Response;

/**
 * B 端验签中间件（Admin 端入站签名校验，§1.12）
 *
 * 协议：RSA-SHA256 签名 + AES-256-CBC 加密
 * 错误码：400/401/402/403/404/406（偏离 §1.3，B 端接口授权保留，详见 §1.12.5）
 */
class AdminSign
{
    private const NONCE_KEY_PREFIX = 'admin:nonce:';

    public function __construct(
        private AppkeyService $appkeyService,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $params = $request->param();

        // ① 校验必填字段（400）
        $requiredCheck = $this->checkRequiredFields($params);
        if ($requiredCheck !== null) {
            return $this->reject($requiredCheck['code'], $requiredCheck['msg']);
        }

        $appId       = (string) $params['app_id'];
        $timestamp   = (int) $params['timestamp'];
        $sign        = (string) $params['sign'];
        $signType    = (string) ($params['sign_type'] ?? 'MD5');
        $nonceStr    = (string) ($params['nonce_str'] ?? '');
        $encryptType = (string) ($params['encrypt_type'] ?? '');
        $bizContent  = (string) ($params['biz_content'] ?? '');

        // ② timestamp 容差（401，§1.12.4）
        $tolerance = (int) config('auth.timestamp_tolerance', 300);
        if (abs(time() - $timestamp) > $tolerance) {
            return $this->reject(401, '请求过期');
        }

        // ③ nonce 防重放（404，§1.12.4）
        if ($nonceStr !== '') {
            $nonceKey = self::NONCE_KEY_PREFIX . $appId . ':' . $nonceStr;
            $nonceTtl = (int) config('auth.nonce_ttl', 300);
            if (Cache::has($nonceKey)) {
                return $this->reject(404, '请求重复');
            }
            Cache::set($nonceKey, 1, $nonceTtl);
        }

        // ④ 查询 app 凭证（402，§1.12.3）
        try {
            $appInfo = $this->appkeyService->getInfo($appId);
            if (empty($appInfo)) {
                return $this->reject(402, 'APP 不存在');
            }
        } catch (\Exception $e) {
            save_log('Appkey 查询失败：' . $e->getMessage(), 2, 'AdminSign验签', 'middleware/AdminSign', [
                'grade' => 1, 'request_data' => ['app_id' => $appId], 'response_data' => $e->getMessage(),
            ]);
            return $this->reject(402, 'APP 不存在');
        }

        // ⑤ 验签（403）
        $apiServer               = new XibeiApiClient();
        $apiServer->appId        = $appInfo['app_id'];
        $apiServer->appKey       = $appInfo['app_key'];
        $apiServer->rsaPublicKey = $appInfo['rsa_public_key'];

        try {
            $signOk = $apiServer->checkSign($params, $signType);
        } catch (\Exception $e) {
            return $this->reject(403, '签名错误');
        }
        if (!$signOk) {
            return $this->reject(403, '签名错误');
        }

        // ⑥ AES 解密 biz_content（406）
        if ($bizContent !== '') {
            try {
                if (strtoupper($encryptType) === 'AES') {
                    $plain = $apiServer->decryptBizContent($bizContent);
                } else {
                    $plain = $bizContent;
                }
                $plainData = json_decode($plain, true);
                if (!is_array($plainData)) {
                    return $this->reject(406, 'biz_content 不是有效 JSON');
                }

                // ⑦ 反射注入到 Request（Controller 透明获取 $this->requestData，详见 §1.12.9）
                $this->injectDecryptedParams($request, $plainData);
            } catch (\Exception $e) {
                return $this->reject(406, $e->getMessage());
            }
        }

        save_log('AdminSign 验签通过', 1, 'AdminSign验签', 'middleware/AdminSign', [
            'request_data' => ['app_id' => $appId, 'path' => $request->pathinfo()],
        ]);

        return $next($request);
    }

    private function checkRequiredFields(array $params): ?array
    {
        if (empty($params['app_id'])) return ['code' => 400, 'msg' => 'app_id 不能为空'];
        if (empty($params['timestamp'])) return ['code' => 400, 'msg' => 'timestamp 不能为空'];
        if (empty($params['sign'])) return ['code' => 400, 'msg' => 'sign 不能为空'];
        return null;
    }

    private function reject(int $code, string $msg): Response
    {
        return Response::create(['code' => $code, 'msg' => $msg, 'data' => []], 'json');
    }

    /**
     * TP8 Request::param 是 protected，无公开 withParam API
     * 反射写入 + 锁 mergeParam=true（防止 BaseController 重组覆盖，§1.12.9）
     */
    private function injectDecryptedParams(Request $request, array $decryptedData): void
    {
        $request->param();  // 触发首次合并

        $ref = new \ReflectionClass($request);
        $paramProp = $ref->getProperty('param');
        $paramProp->setAccessible(true);
        $paramProp->setValue($request, array_merge($request->param(), $decryptedData));

        $mergeProp = $ref->getProperty('mergeParam');
        $mergeProp->setAccessible(true);
        $mergeProp->setValue($request, true);
    }
}
