<?php
declare(strict_types=1);

namespace lib;

use Exception;
use sdk\HttpCode;
use think\facade\Request;

/**
 * XibeiApiClient
 *
 * 西贝营销中心 API 客户端 · 验签 / 加密 / 请求 标准实现
 * 签名支持 MD5 / RSA；加密支持 AES-256-CBC
 */
class XibeiApiClient
{
    /**
     * 应用id
     *
     * @var string
     */
    public $appId;

    /**
     * 应用秘钥 用于数据加密
     *
     * @var string
     */
    public $appKey;

    /**
     * 版本
     *
     * @var string
     */
    public $version = "1.0";

    /**
     * 接口请求超时时间
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * 签名类型
     * 支持md5和rsa
     *
     * @var string
     */
    public $signType = 'MD5';

    /**
     * 加密类型 为空表示不加密
     *
     * @var string
     */
    public $encryptType = "AES";

    /**
     * RSA公钥
     *
     * @var string
     */
    public $rsaPublicKey = '';

    /**
     * RSA私钥
     *
     * @var string
     */
    public $rsaPrivateKey = '';

    /**
     * 加密方法
     *
     * @param string $str
     * @param string $screct_key
     *
     * @return string
     */
    public function encrypt($str, $screct_key)
    {
        $cipher = "AES-256-CBC";

        // 设置全0的IV
        $iv_size = openssl_cipher_iv_length($cipher); // 16
        $iv      = str_repeat("\0", $iv_size);

        $encrypt_str = openssl_encrypt($str, $cipher, $screct_key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($encrypt_str);
    }

    /**
     * 解密方法
     *
     * @param string $str
     * @param string $screct_key
     *
     * @return string|false
     */
    public function decrypt($str, $screct_key)
    {
        $str    = base64_decode($str);
        $cipher = "AES-256-CBC";

        // 设置全0的IV
        $iv_size = openssl_cipher_iv_length($cipher); // 16
        $iv      = str_repeat("\0", $iv_size);

        $decrypt_str = openssl_decrypt($str, $cipher, $screct_key, OPENSSL_RAW_DATA, $iv);

        return $decrypt_str;
    }

    /**
     * 执行请求
     *
     * @param string $url
     * @param array  $data
     *
     * @return bool|mixed
     * @throws Exception
     */
    public function execute($url, array $data = [])
    {
        // 组装系统参数
        $requestParams                 = [];
        $requestParams["app_id"]       = $this->appId;
        $requestParams["version"]      = $this->version;
        $requestParams["timestamp"]    = time();
        $requestParams['nonce_str']    = $this->generateNonceStr();
        $requestParams["encrypt_type"] = $this->encryptType;
        $requestParams['biz_content']  = json_encode($data);
        $requestParams["sign_type"]    = $this->signType;

        // 执行加密
        if ($this->encryptType == 'AES' && !empty($data)) {
            $requestParams['biz_content'] = $this->encrypt($requestParams['biz_content'], $this->appKey);
        }

        // 执行签名
        $requestParams["sign"] = $this->generateSign($requestParams, $this->signType);

        // 发起HTTP请求
        try {
            $resp = $this->post($url, json_encode($requestParams));
        } catch (\Exception $e) {
            $this->log($url, $data, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());

            return false;
        }

        // 解析返回结果
        $respObject = json_decode($resp, true);

        // 返回的HTTP文本不是标准JSON，记下错误日志
        if (null === $respObject) {
            $this->log($url, $data, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);

            return false;
        }

        // 解密
        if (
            $this->encryptType == 'AES'
            && isset($respObject['data'])
            && !empty($respObject['data'])
            && is_string($respObject['data'])
        ) {
            $bizContent = $this->decrypt($respObject['data'], $this->appKey);

            $respObject['data'] = json_decode((string)$bizContent, true);
        }

        return $respObject;
    }

    public static function verify($data, $sign, $rsaPublicKey)
    {
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($rsaPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        // 调用openssl内置方法验签，返回bool值
        $result = (openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1);

        return $result;
    }

    /**
     * 验证入站请求签名（用 rsaPublicKey 验签，与 generateSign 对称，§1.12.2）
     */
    public function checkSign(array $params, string $signType = 'RSA'): bool
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        $signParams = $params;
        unset($signParams['sign']);
        $signContent = $this->getSignContent($signParams);

        if ($signType === 'MD5') {
            $expected = $this->md5Sign($signContent, $this->appKey);
            return hash_equals($expected, $sign);
        }

        $res = "-----BEGIN PUBLIC KEY-----\n"
            . wordwrap($this->rsaPublicKey, 64, "\n", true)
            . "\n-----END PUBLIC KEY-----";
        return openssl_verify($signContent, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * 解密入站 biz_content（封装 decrypt，默认用 appKey，调用方更简洁）
     */
    public function decryptBizContent(string $cipher): string
    {
        $plain = $this->decrypt($cipher, $this->appKey);
        if ($plain === false) {
            throw new \Exception('biz_content 解密失败：' . openssl_error_string());
        }
        return (string)$plain;
    }

    /**
     * 生成签名
     *
     * @param array  $params
     * @param string $signType
     *
     * @return string
     */
    private function generateSign($params, $signType = "RSA")
    {
        $signContent = $this->getSignContent($params);

        switch ($signType) {
            case 'MD5':
                $sign = $this->md5Sign($signContent, $this->appKey);
                break;
            case 'RSA':
                $sign = $this->rsaSign($signContent, $this->rsaPrivateKey);
                break;
            default:
                $sign = '';
        }

        return $sign;
    }

    /**
     * 生成MD5签名
     *
     * @param string $content
     * @param string $key
     *
     * @return string
     */
    private function md5Sign($content, $key)
    {
        $sign = md5($content . '&key=' . $key);

        return $sign;
    }

    /**
     * 生成RSA签名
     *
     * @param string $data
     * @param string $rsaPrivateKey
     *
     * @return string
     */
    private function rsaSign($data, $rsaPrivateKey)
    {
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($rsaPrivateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);

        $sign = base64_encode($sign);

        return $sign;
    }

    /**
     * 生成待签名字符串
     *
     * @param array $params
     *
     * @return string
     */
    public function getSignContent($params = [])
    {
        $paramString = "";
        if (!empty($params)) {
            ksort($params);
            foreach ($params as $key => $value) {
                if ($value !== '' && !is_array($value) && "@" != substr((string)$value, 0, 1)) {
                    $paramString .= $key . "=" . $value . '&';
                }
            }
        }

        return rtrim($paramString, '&');
    }

    /**
     * 生成随机字串
     *
     * @param int $length 长度，默认为16，最长为32字节
     *
     * @return string
     */
    private function generateNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $str;
    }

    /**
     * 发送请求
     *
     * @param string $url
     * @param string $strPOST
     *
     * @return bool|string
     * @throws Exception
     */
    public function post($url, $strPOST)
    {
        $ch = curl_init();
        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $headers = [
            "content-type: application/json"
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $strPOST);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        $response = curl_exec($ch);
        save_log($response, 1, 'API接口请求返回原始数据', 'api/post', [
            'model'         => '营销中心API',
            'request_data'  => $strPOST,
            'url'           => $url,
            'response_data' => $response
        ]);
        if (curl_errno($ch)) {
            $remark = "请求接口" . $url . '失败';
            save_log($remark, 2, 'API接口请求', Request::pathinfo(),
                [
                    'model'        => '营销中心API',
                    'request_data' => $strPOST,
                    'request_msg'  => 'API请求异常：' . HttpCode::getCurlCode(curl_errno($ch)) . '，请求路径：' . Request::pathinfo() .
                        '，请求地址；' . $url
                ]);
            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                $remark = "请求接口" . $url . '失败';
                save_log($remark, 2, 'API接口请求', Request::pathinfo(),
                    [
                        'model'        => '营销中心API',
                        'request_data' => $strPOST,
                        'request_msg'  => 'API请求异常：' . HttpCode::get($httpStatusCode) . '，请求路径：' . Request::pathinfo() . '，请求地址；' . $url
                    ]);
                throw new Exception((string)$response, $httpStatusCode);
            }
        }

        curl_close($ch);

        return $response;
    }

    /**
     * http错误日志记录
     *
     * @param string $requestUrl
     * @param mixed  $requestData
     * @param mixed  $errorCode
     * @param mixed  $responseTxt
     */
    protected function log($requestUrl, $requestData, $errorCode, $responseTxt)
    {
        save_log([
            'request_url'   => $requestUrl,
            'request_data'  => $requestData,
            'error_code'    => $errorCode,
            'response_text' => $responseTxt,
        ], 1, '接口请求失败');
    }
}
