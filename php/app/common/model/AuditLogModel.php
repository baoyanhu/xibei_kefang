<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 审计日志模型，记录基础配置等需要可追溯的变更。
 */
class AuditLogModel extends Model
{
    /** 样式配置保存动作。 */
    public const ACTION_SAVE_STYLE_CONFIG = 'save_style_config';

    /** 商户样式配置审计目标类型。 */
    public const TARGET_MERCHANT_STYLE_CONFIG = 'merchant_style_config';

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'audit_logs';

    /** 允许写入的字段白名单，确保审计内容按表契约入库。 */
    protected $field = [
        'id',
        'operator',
        'action',
        'target_type',
        'target_id',
        'payload',
        'operated_at',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射，审计详情以 JSON 结构读写。 */
    protected $type = [
        'id'        => 'integer',
        'target_id' => 'integer',
        'payload'   => 'json',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
