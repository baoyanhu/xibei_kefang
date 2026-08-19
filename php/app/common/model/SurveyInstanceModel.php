<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 问卷实例模型，承载结账生成的评价推送实例与状态流转。
 */
class SurveyInstanceModel extends Model
{
    /** 实例状态：待推送。 */
    public const STATUS_PENDING_PUSH = 1;

    /** 实例状态：已推送。 */
    public const STATUS_PUSHED = 2;

    /** 实例状态：已打开。 */
    public const STATUS_OPENED = 3;

    /** 实例状态：已提交。 */
    public const STATUS_SUBMITTED = 4;

    /** 实例状态：已失效。 */
    public const STATUS_EXPIRED = 5;

    /** 实例状态：推送失败。 */
    public const STATUS_PUSH_FAILED = 6;

    /** 状态文本映射。 */
    public static array $STATUS_TEXTS = [
        self::STATUS_PENDING_PUSH => '待推送',
        self::STATUS_PUSHED       => '已推送',
        self::STATUS_OPENED       => '已打开',
        self::STATUS_SUBMITTED    => '已提交',
        self::STATUS_EXPIRED      => '已失效',
        self::STATUS_PUSH_FAILED  => '推送失败',
    ];

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'survey_instances';

    /**
     * 允许写入的字段白名单，防止请求参数越权入库。
     * expire_at 为 STORED 生成列，数据库自动计算，禁止写入，不在白名单内。
     */
    protected $field = [
        'id',
        'questionnaire_id',
        'order_no',
        'member_card_no',
        'openid',
        'unionid',
        'phone',
        'store_code',
        'store_name',
        'checkout_at',
        'validity_days',
        'status',
        'pushed_at',
        'opened_at',
        'submitted_at',
        'raw_data',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'               => 'integer',
        'questionnaire_id' => 'integer',
        'validity_days'    => 'integer',
        'status'           => 'integer',
        'raw_data'         => 'json',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
