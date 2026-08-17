<?php
declare(strict_types=1);

namespace app\service\admin;

/**
 * 门店业务逻辑层（B 端）
 * 示例使用 mock 数据，真实业务替换为 StoreModel 查询
 */
class StoreService
{
    /**
     * 门店列表（B 端 — mock 数据演示）
     *
     * @param array $filters 筛选条件（page / page_size / store_name 等）
     * @return array {count: int, list: array}（§1.3.1 列表返回结构）
     */
    public function getList(array $filters): array
    {
        // mock 门店数据（真实业务改用 StoreModel::order(...)->paginate(...)）
        $allStores = [
            ['id' => 1, 'store_code' => 'BJ001', 'store_name' => '北京王府井店', 'business_status' => 1],
            ['id' => 2, 'store_code' => 'SH001', 'store_name' => '上海南京路店', 'business_status' => 1],
            ['id' => 3, 'store_code' => 'GZ001', 'store_name' => '广州天河城店', 'business_status' => 1],
            ['id' => 4, 'store_code' => 'SZ001', 'store_name' => '深圳万象城店', 'business_status' => 0],
            ['id' => 5, 'store_code' => 'CD001', 'store_name' => '成都春熙路店', 'business_status' => 1],
        ];

        // 简单筛选（按门店名称模糊匹配，演示用）
        $keyword = trim((string)($filters['store_name'] ?? ''));
        if ($keyword !== '') {
            $allStores = array_filter($allStores, fn($s) => str_contains($s['store_name'], $keyword));
            $allStores = array_values($allStores);
        }

        // 分页（演示用，真实业务用 Model paginate）
        $page     = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, (int) ($filters['page_size'] ?? 10));
        $count    = count($allStores);
        $list     = array_slice($allStores, ($page - 1) * $pageSize, $pageSize);

        return [
            'count' => $count,
            'list'  => $list,
        ];
    }
}
