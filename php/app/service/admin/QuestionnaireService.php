<?php
declare(strict_types=1);

namespace app\service\admin;

use app\common\model\AuditLogModel;
use app\common\model\MerchantConfigModel;
use app\common\model\MerchantModel;
use think\facade\Db;

/**
 * B 端问卷配置业务服务，负责触发时机与奖励形态配置的校验、读写和变更审计。
 */
class QuestionnaireService
{
    /** 触发时机：立即推送。 */
    private const TRIGGER_MODE_IMMEDIATE = 1;

    /** 触发时机：延迟推送。 */
    private const TRIGGER_MODE_DELAYED = 2;

    /** 延迟推送分钟数上限（最长 24 小时，PRD 契约）。 */
    private const DELAY_MINUTES_MAX = 1440;

    /**
     * 查询指定商户的问卷配置。
     *
     * @param int $merchantId 商户 ID。
     * @return array 问卷配置详情。
     */
    public function getDetail(int $merchantId): array
    {
        $this->assertMerchantExists($merchantId);

        // 问卷配置按商户+配置类型唯一，与样式配置同表不同 type。
        $config = MerchantConfigModel::where('merchant_id', $merchantId)
            ->where('config_type', MerchantConfigModel::TYPE_REWARD)
            ->find();

        $payload = $config ? $this->normalizePayload($config->getAttr('config_payload')) : [];

        return $this->formatResponse($merchantId, $payload);
    }

    /**
     * 新增或更新指定商户的问卷配置。
     *
     * @param array  $data     已解密的业务请求参数。
     * @param string $operator 验签调用方或当前操作人标识。
     * @return array 保存后的问卷配置。
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
                ->where('config_type', MerchantConfigModel::TYPE_REWARD)
                ->lock(true)
                ->find();
            $before = $config
                ? $this->formatResponse($input['merchant_id'], $this->normalizePayload($config->getAttr('config_payload')))
                : $this->formatResponse($input['merchant_id'], []);

            $payload = [
                'triggerMode'     => $input['trigger_mode'],
                'delayMinutes'    => $input['delay_minutes'],
                'rewardPoints'    => $input['reward_points'],
                'points'          => $input['points'],
                'rewardCoupon'    => $input['reward_coupon'],
                'couponTemplateId' => $input['coupon_template_id'],
            ];

            // 表中已有配置则原行更新，否则按唯一约束创建首条问卷配置。
            if ($config) {
                $config->save(['config_payload' => $payload]);
            } else {
                $config = new MerchantConfigModel();
                $config->save([
                    'merchant_id'    => $input['merchant_id'],
                    'config_type'    => MerchantConfigModel::TYPE_REWARD,
                    'config_payload' => $payload,
                ]);
            }

            $after = $this->formatResponse($input['merchant_id'], $payload);
            $audit = new AuditLogModel();
            $audit->save([
                'operator'    => trim($operator) !== '' ? trim($operator) : 'system',
                'action'      => AuditLogModel::ACTION_SAVE_QUESTIONNAIRE_CONFIG,
                'target_type' => AuditLogModel::TARGET_MERCHANT_QUESTIONNAIRE_CONFIG,
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
     * 校验并规整保存入参，确保触发时机和奖励形态符合接口契约。
     *
     * @param array $data 原始业务请求参数。
     * @return array 规整后的入库参数。
     */
    private function validateInput(array $data): array
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $triggerMode = (int) ($data['trigger_mode'] ?? 0);
        $delayMinutes = isset($data['delay_minutes']) && $data['delay_minutes'] !== '' ? (int) $data['delay_minutes'] : null;
        $rewardPoints = (int) ($data['reward_points'] ?? 0);
        $points = (int) ($data['points'] ?? 0);
        $rewardCoupon = (int) ($data['reward_coupon'] ?? 0);
        $couponTemplateId = trim((string) ($data['coupon_template_id'] ?? ''));

        // 商户 ID 是配置唯一归属条件，缺失时不能继续。
        if ($merchantId <= 0) {
            exception('缺少参数 merchant_id');
        }
        // 触发时机只允许立即推送或延迟推送。
        if (!in_array($triggerMode, [self::TRIGGER_MODE_IMMEDIATE, self::TRIGGER_MODE_DELAYED], true)) {
            exception('trigger_mode 只能为 1（立即推送）或 2（延迟推送）');
        }
        // 立即推送没有延迟概念，强制置空防止脏值入库。
        if ($triggerMode === self::TRIGGER_MODE_IMMEDIATE) {
            $delayMinutes = null;
        }
        // 延迟推送必须带分钟数，且在 1-1440（最长 24 小时）范围内。
        if ($triggerMode === self::TRIGGER_MODE_DELAYED) {
            if ($delayMinutes === null || $delayMinutes < 1 || $delayMinutes > self::DELAY_MINUTES_MAX) {
                exception('delay_minutes 必填，范围 1-1440 分钟（最长 24 小时）');
            }
        }

        // 积分与券开关均为独立布尔，允许同时启用或同时关闭。
        if (!in_array($rewardPoints, [0, 1], true)) {
            exception('reward_points 只能为 0 或 1');
        }
        if (!in_array($rewardCoupon, [0, 1], true)) {
            exception('reward_coupon 只能为 0 或 1');
        }
        // 勾选积分时数量必填正整数；未勾选强制归零，避免残留旧值。
        if ($rewardPoints === 1 && $points <= 0) {
            exception('reward_points=1 时 points 必填且为正整数');
        }
        if ($rewardPoints === 0) {
            $points = 0;
        }
        // 勾选券时必须绑定微生活券模板 ID；未勾选强制置空。
        if ($rewardCoupon === 1 && $couponTemplateId === '') {
            exception('reward_coupon=1 时 coupon_template_id 必填');
        }
        if ($rewardCoupon === 0) {
            $couponTemplateId = '';
        }

        return [
            'merchant_id'       => $merchantId,
            'trigger_mode'      => $triggerMode,
            'delay_minutes'     => $delayMinutes,
            'reward_points'     => $rewardPoints,
            'points'            => $points,
            'reward_coupon'     => $rewardCoupon,
            'coupon_template_id' => $couponTemplateId,
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
     * @param array $payload    内部问卷配置。
     * @return array 对外问卷配置。
     */
    private function formatResponse(int $merchantId, array $payload): array
    {
        // 未保存配置时返回可安全渲染的默认值，便于前端首次录入。
        return [
            'merchant_id'       => $merchantId,
            'trigger_mode'      => (int) ($payload['triggerMode'] ?? self::TRIGGER_MODE_IMMEDIATE),
            'delay_minutes'     => isset($payload['delayMinutes']) ? (int) $payload['delayMinutes'] : null,
            'reward_points'     => (int) ($payload['rewardPoints'] ?? 0),
            'points'            => (int) ($payload['points'] ?? 0),
            'reward_coupon'     => (int) ($payload['rewardCoupon'] ?? 0),
            'coupon_template_id' => (string) ($payload['couponTemplateId'] ?? ''),
        ];
    }
}
