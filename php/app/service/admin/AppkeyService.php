<?php
declare(strict_types=1);

namespace app\service\admin;

use sdk\Mdc;
use think\facade\Cache;

/**
 * Appkey 业务服务（B 端，§1.12.3）
 *
 * 从 MDC 拉取调用方授权清单 + Redis 缓存（TTL=3600）
 */
class AppkeyService
{
    private const CACHE_KEY_PREFIX = 'appkey:';
    private const CACHE_TTL = 3600;

    public function __construct(
        private Mdc $mdc,
    ) {
    }

    /**
     * 根据 app_id 获取调用方凭证（§1.12.3 凭证链路）
     *
     * @return array{app_id:string,app_name:string,app_key:string,rsa_public_key:string}
     * @throws \Exception
     */
    public function getInfo(string $appId): array
    {
        save_log('查询 Appkey 请求日志', 1, '查询Appkey', 'service/admin/AppkeyService', [
            'request_data' => ['app_id' => $appId],
        ]);

        try {
            $cached = Cache::get(self::CACHE_KEY_PREFIX . $appId);
            if (!empty($cached)) {
                save_log('查询 Appkey 执行完成日志（命中缓存）', 1, '查询Appkey', 'service/admin/AppkeyService', [
                    'response_data' => ['source' => 'cache', 'app_id' => $appId],
                ]);
                return $cached;
            }

            $data = $this->mdc->getAuthList();
            if ($data === false) {
                exception('授权列表获取失败：' . $this->mdc->errMsg);
            }

            $list = $data['list'] ?? [];
            if (empty($list)) {
                exception('授权列表为空');
            }
            foreach ($list as $v) {
                Cache::set(self::CACHE_KEY_PREFIX . $v['token'], $this->normalizeAppInfo($v), self::CACHE_TTL);
            }

            $target = Cache::get(self::CACHE_KEY_PREFIX . $appId);
            if (empty($target)) {
                exception('appid 不存在：' . $appId);
            }

            save_log('查询 Appkey 执行完成日志（MDC 拉取）', 1, '查询Appkey', 'service/admin/AppkeyService', [
                'response_data' => ['source' => 'mdc', 'app_id' => $appId],
            ]);
            return $target;
        } catch (\Exception $e) {
            $remark = '查询 Appkey 失败，原因：' . $e->getMessage();
            save_log($remark, 2, '查询Appkey', 'service/admin/AppkeyService', [
                'grade' => 1,
                'request_data' => ['app_id' => $appId],
                'response_data' => $remark . ' 错误编码：' . $e->getCode() . ' 错误行：' . $e->getLine() . ' 错误文件：' . $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * MDC 字段 → 内部字段映射（§1.12.3 / mdc-sdk SKILL）
     */
    private function normalizeAppInfo(array $row): array
    {
        return [
            'app_id'         => (string) ($row['token'] ?? ''),
            'app_name'       => (string) ($row['dept'] ?? ''),
            'app_key'        => (string) ($row['key'] ?? ''),
            'rsa_public_key' => (string) ($row['rsa_public_key'] ?? ''),
        ];
    }
}
