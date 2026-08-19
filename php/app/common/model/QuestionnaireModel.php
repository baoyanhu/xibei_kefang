<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 问卷模板模型，承载问卷主表数据与同商户启用互斥标记。
 */
class QuestionnaireModel extends Model
{
    use SoftDelete;

    /** 触发模式：立即推送。 */
    public const TRIGGER_IMMEDIATE = 1;

    /** 触发模式：延迟推送。 */
    public const TRIGGER_DELAY = 2;

    /** 问卷状态：草稿。 */
    public const STATUS_DRAFT = 'draft';

    /** 问卷状态：禁用。 */
    public const STATUS_DISABLED = 'disabled';

    /** 问卷状态：启用。 */
    public const STATUS_ENABLED = 'enabled';

    /** 状态文本映射。 */
    public static array $STATUS_TEXTS = [
        self::STATUS_DRAFT    => '草稿',
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED  => '启用',
    ];

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'questionnaires';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'merchant_id',
        'style_id',
        'name',
        'trigger_mode',
        'delay_minutes',
        'validity_days',
        'fallback_enabled',
        'dish_link_enabled',
        'active_flag',
        'status',
        'updated_by',
        'create_time',
        'update_time',
        'delete_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'                => 'integer',
        'merchant_id'       => 'integer',
        'style_id'          => 'integer',
        'trigger_mode'      => 'integer',
        'delay_minutes'     => 'integer',
        'validity_days'     => 'integer',
        'fallback_enabled'  => 'integer',
        'dish_link_enabled' => 'integer',
        'active_flag'       => 'integer',
        'updated_by'        => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';

    /** 软删除字段，NULL 表示未删除。 */
    protected $deleteTime = 'delete_time';

    /** 未删除记录的 delete_time 取值。 */
    protected $defaultSoftDelete = null;
}
