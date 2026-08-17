<?php
declare(strict_types=1);

namespace app\service\api;

/**
 * 门店业务逻辑层（C 端示例，code-style §5.10.4 / §5.12）
 * 示例说明：使用 mock 数据演示（不连数据库），真实业务改用 StoreModel 查询。
 * C 端差异化：只展示营业中门店 + 按鉴权注入的权限门店集合（store_code 参数）过滤。
 */
class StoreService
{
    /**
     * 门店列表（C 端 — 只返回营业中 + 权限内的门店）
     *
     * @param array $filters 筛选条件（store_code 由 BaseController 默认注入 = ApiAuth 权限门店集合）
     * @return array {count: int, list: array}
     */
    public function getList(array $filters): array
    {
        // mock 门店数据（store_id 对齐 RMS 权限口径，真实业务改用 StoreModel::where(...)->paginate(...)）
        $allStores = [
            ['id' => 1, 'store_id' => '932272163', 'store_code' => 'BJ001', 'store_name' => '北京王府井店', 'business_status' => 1],
            ['id' => 2, 'store_id' => '932272164', 'store_code' => 'SH001', 'store_name' => '上海南京路店', 'business_status' => 1],
            ['id' => 3, 'store_id' => '932272165', 'store_code' => 'GZ001', 'store_name' => '广州天河城店', 'business_status' => 1],
            ['id' => 4, 'store_id' => '932272166', 'store_code' => 'SZ001', 'store_name' => '深圳万象城店', 'business_status' => 0],
            ['id' => 5, 'store_id' => '932272167', 'store_code' => 'CD001', 'store_name' => '成都春熙路店', 'business_status' => 1],
        ];

        // C 端差异化：只展示营业中的门店（business_status=1）
        $openStores = array_filter($allStores, fn($s) => $s['business_status'] === 1);
        $openStores = array_values($openStores);

        // 权限门店过滤（ApiAuth 注入的 store_code = 授权 store_id 逗号集合，逐个校验过权限）
        $authIds = array_filter(explode(',', (string) ($filters['store_code'] ?? '')), fn($v) => trim((string)$v) !== '');
        if (!empty($authIds)) {
            $openStores = array_filter($openStores, fn($s) => in_array($s['store_id'], $authIds, false));
            $openStores = array_values($openStores);
        }

        // 按门店名称模糊筛选
        $keyword = trim((string) ($filters['store_name'] ?? ''));
        if ($keyword !== '') {
            $openStores = array_filter($openStores, fn($s) => str_contains($s['store_name'], $keyword));
            $openStores = array_values($openStores);
        }

        return [
            'count' => count($openStores),
            'list'  => $openStores,
        ];
    }
}
