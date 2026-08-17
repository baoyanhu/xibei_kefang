<?php
declare(strict_types=1);

namespace app\service\api;

use sdk\Rms;
use think\facade\Cache;

/**
 * C 端鉴权业务逻辑层
 * 调 RMS 拉员工角色权限 → store_id 集合存 Redis / Cache（默认 1 小时）
 * 缓存单 key：md5(mchId . employeeCode) → store_id 集合（逗号分隔，鉴权用）
 */
class AuthService
{
    /**
     * 获取鉴权数据（缓存优先：key=md5(mchId.employeeCode) 命中直接返回 store_id 集合，未命中才调 RMS）
     * menu_id 固定值走配置 config('rms.menu_id')，不进方法入参
     * 缓存过期时间走配置 config('rms.token_ttl')（秒，默认 1 小时）
     *
     * @param string $mchId 品牌ID
     * @param string $employeeCode 员工编码
     *
     * @return string store_id 集合（逗号分隔，如 "932272163,996571447,..."）
     */
    public function getAuth(string $mchId, string $employeeCode): string
    {
        // ① 配置读取：menu_id 必须已配置（如果凭证未配，走降级允许测试，否则按配置）
        $menuId   = trim((string) config('rms.menu_id'));
        $tokenTtl = (int) config('rms.token_ttl', 3600);
        
        // 凭证存在时检查 menu_id
        $hasAuth = trim((string) config('auth.auth_appid')) !== '' && trim((string) config('auth.auth_key')) !== '';
        if ($hasAuth && $menuId === '') {
            exception('未配置 rms.menu_id');
        }
        if ($tokenTtl <= 0) {
            exception('rms.token_ttl 配置非法');
        }

        // ② 校验用户是否已缓存（key=md5(mchId.employeeCode)，命中直接返回 store_id 集合）
        $authKey = md5($mchId . $employeeCode);
        try {
            $cached = Cache::get($authKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存未就绪或驱动未配置时跳过缓存直接调 RMS
        }

        // ③ 缓存未命中才调 RMS 拉员工角色权限（失败时 RMS 侧已带 errCode/errMsg）
        $rms    = new Rms();
        $result = $rms->getRoleList([
            'mch_id'        => $mchId,
            'employee_code' => $employeeCode,
            'menu_id'       => $menuId,
        ]);
        if ($result === false) {
            exception('拉取角色权限失败：' . $rms->errMsg . '（' . $rms->errCode . '）');
        }

        // ④ 提取 store_id 集合（无管辖门店则拦截）
        $storeIds = array_column($result['store_list'] ?? [], 'store_id');
        $storeIds = array_filter($storeIds, fn($id) => trim((string) $id) !== '');
        if (empty($storeIds)) {
            exception('该员工无可管辖门店');
        }

        // ⑤ 落缓存（key=md5(mchId.employeeCode)，value=store_id 逗号分隔集合，TTL=token_ttl）
        $storeStr = implode(',', array_values($storeIds));
        try {
            Cache::set($authKey, $storeStr, $tokenTtl);
        } catch (\Throwable $e) {
            // 缓存写入异常不阻断主链路
        }

        return $storeStr;
    }
}
