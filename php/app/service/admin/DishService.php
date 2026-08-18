<?php
declare(strict_types=1);

namespace app\service\admin;

use app\common\model\AuditLogModel;
use app\common\model\MerchantConfigModel;
use app\common\model\MerchantModel;
use think\facade\Db;

/**
 * B 端菜品配置业务服务，负责菜品数据关联开关的校验、读写和变更审计。
 */
class DishService
{
    /**
     * 查询指定商户的菜品配置。
     *
     * @param int $merchantId 商户 ID。
     * @return array 菜品配置详情。
     */
    public function getDetail(int $merchantId): array
    {
        $this->assertMerchantExists($merchantId);

        // 菜品配置按商户+配置类型唯一，与样式/问卷配置同表不同 type。
        $config = MerchantConfigModel::where('merchant_id', $merchantId)
            ->where('config_type', MerchantConfigModel::TYPE_DISH)
            ->find();

        $payload = $config ? $this->normalizePayload($config->getAttr('config_payload')) : [];

        return $this->formatResponse($merchantId, $payload);
    }

    /**
     * 新增或更新指定商户的菜品配置。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 保存后的菜品配置。
     */
    public function save(array $data, string $operator): array
    {
        $input = $this->validateInput($data);

        // 配置和审计必须同成同败，避免出现无变更记录的配置更新。
        return Db::transaction(function () use ($input, $operator): array {
            // 锁定商户行串行化同一商户首次建配置，防止并发插入触发唯一索引冲突。
            $merchant = MerchantModel::where('id', $input['merchant_id'])->lock(true)->find();
            // 逻辑外键不存在时必须拒绝保存，避免生成孤立配置。
            if (!$merchant) {
                exception('商户不存在');
            }

            // 当前配置锁定后再 upsert，保证审计中的 before 是本次更新的真实前置值。
            $config = MerchantConfigModel::where('merchant_id', $input['merchant_id'])
                ->where('config_type', MerchantConfigModel::TYPE_DISH)
                ->lock(true)
                ->find();
            $before = $config
                ? $this->formatResponse($input['merchant_id'], $this->normalizePayload($config->getAttr('config_payload')))
                : $this->formatResponse($input['merchant_id'], []);

            $payload = [
                'linkDishData' => $input['dish_link_enabled'] === 1,
            ];

            // 表中已有配置则原行更新，否则按唯一约束创建首条菜品配置。
            if ($config) {
                $config->save(['config_payload' => $payload]);
            } else {
                $config = new MerchantConfigModel();
                $config->save([
                    'merchant_id'    => $input['merchant_id'],
                    'config_type'    => MerchantConfigModel::TYPE_DISH,
                    'config_payload' => $payload,
                ]);
            }

            $after = $this->formatResponse($input['merchant_id'], $payload);
            $audit = new AuditLogModel();
            $audit->save([
                'operator'    => trim($operator) !== '' ? trim($operator) : 'system',
                'action'      => AuditLogModel::ACTION_SAVE_DISH_CONFIG,
                'target_type' => AuditLogModel::TARGET_MERCHANT_DISH_CONFIG,
                'target_id'   => (int) $config->id,
                'payload'     => [
                    'merchant_id' => $input['merchant_id'],
                    'before'      => $before,
                    'after'       => $after,
                ],
            ]);

            return $after;
        });
    }

    /**
     * 校验并规整保存入参，确保开关符合接口契约。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的入库参数。
     */
    private function validateInput(array $data): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $dishLinkEnabled = (int) ($data['dish_link_enabled'] ?? 0);

        // 商户 ID 是配置唯一归属条件，缺失时不能继续。
        if ($merchantId <= 0) {
            exception('缺少参数 merchant_id');
        }
        // 开关只接受启用或禁用两种取值。
        if (!in_array($dishLinkEnabled, [0, 1], true)) {
            exception('dish_link_enabled 只能为 0（禁用）或 1（启用）');
        }

        return [
            'merchant_id'       => $merchantId,
            'dish_link_enabled' => $dishLinkEnabled,
        ];
    }

    /**
     * 确认商户存在，避免返回或创建无归属的基础配置。
     *
     * @param int $merchantId 商户 ID。
     * @return void
     */
    private function assertMerchantExists(int $merchantId): void
    {
        // 详情查询也必须有明确的商户归属。
        if ($merchantId <= 0) {
            exception('缺少参数 merchant_id');
        }

        // merchant_configs 没有物理外键，因此由 Service 显式保证逻辑关联有效。
        if (!MerchantModel::where('id', $merchantId)->find()) {
            exception('商户不存在');
        }
    }

    /**
     * 将模型层 JSON 属性统一转为数组，兼容已有字符串数据。
     *
     * @param mixed $payload 数据库返回的配置内容。
     * @return array 可用的配置数组。
     */
    private function normalizePayload(mixed $payload): array
    {
        // Model JSON 类型已解析时直接使用，避免二次解码。
        if (is_array($payload)) {
            return $payload;
        }

        $decoded = json_decode((string) $payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 将内部 JSON 键转换为稳定的对外 snake_case 接口字段。
     *
     * @param int   $merchantId 商户 ID。
     * @param array $payload    内部菜品配置。
     * @return array 对外菜品配置。
     */
    private function formatResponse(int $merchantId, array $payload): array
    {
        // 未保存配置时默认禁用（保守口径），便于前端首次录入。
        return [
            'merchant_id'       => $merchantId,
            'dish_link_enabled' => !empty($payload['linkDishData']) ? 1 : 0,
        ];
    }
}
