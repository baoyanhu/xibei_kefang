<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 激励发放记录模型，承载答题完成后的积分/券发放流水与状态。
 */
class RewardRecordModel extends Model
{
    /** 激励类型：积分。 */
    public const REWARD_TYPE_POINTS = 1;

    /** 激励类型：券。 */
    public const REWARD_TYPE_COUPON = 2;

    /** 激励类型文本映射。 */
    public static array $REWARD_TYPE_TEXTS = [
        self::REWARD_TYPE_POINTS => '积分',
        self::REWARD_TYPE_COUPON => '券',
    ];

    /** 发放状态：待发放。 */
    public const STATUS_PENDING = 1;

    /** 发放状态：发放中。 */
    public const STATUS_GRANTING = 2;

    /** 发放状态：已发放。 */
    public const STATUS_GRANTED = 3;

    /** 发放状态：发放失败。 */
    public const STATUS_GRANT_FAILED = 4;

    /** 发放状态：已人工补发。 */
    public const STATUS_MANUAL_GRANTED = 5;

    /** 状态文本映射。 */
    public static array $STATUS_TEXTS = [
        self::STATUS_PENDING        => '待发放',
        self::STATUS_GRANTING       => '发放中',
        self::STATUS_GRANTED        => '已发放',
        self::STATUS_GRANT_FAILED   => '发放失败',
        self::STATUS_MANUAL_GRANTED => '已人工补发',
    ];

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'reward_records';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'instance_id',
        'reward_type',
        'points',
        'coupon_template_id',
        'status',
        'grant_serial_no',
        'coupon_no',
        'granted_at',
        'failure_reason',
        'retry_count',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'            => 'integer',
        'instance_id'   => 'integer',
        'reward_type'   => 'integer',
        'points'        => 'integer',
        'status'        => 'integer',
        'retry_count'   => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
