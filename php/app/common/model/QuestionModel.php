<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 题目模型，7 种题型的差异化配置存于 config JSON。
 */
class QuestionModel extends Model
{
    /** 题型：单选。 */
    public const TYPE_SINGLE = 1;

    /** 题型：多选。 */
    public const TYPE_MULTI = 2;

    /** 题型：NPS 打分。 */
    public const TYPE_NPS = 3;

    /** 题型：多维度打分。 */
    public const TYPE_DIMENSION = 4;

    /** 题型：图片上传。 */
    public const TYPE_IMAGE = 5;

    /** 题型：文本输入。 */
    public const TYPE_TEXT = 6;

    /** 题型：菜品评价。 */
    public const TYPE_DISH = 7;

    /** 题型文本映射。 */
    public static array $TYPE_TEXTS = [
        self::TYPE_SINGLE    => '单选',
        self::TYPE_MULTI     => '多选',
        self::TYPE_NPS       => 'NPS',
        self::TYPE_DIMENSION => '维度',
        self::TYPE_IMAGE     => '图片',
        self::TYPE_TEXT      => '文本',
        self::TYPE_DISH      => '菜品',
    ];

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'questions';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'group_id',
        'type',
        'title',
        'sort_order',
        'required',
        'config',
        'option_jumps',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射，配置与跳题规则在模型层自动编解码。 */
    protected $type = [
        'id'            => 'integer',
        'group_id'      => 'integer',
        'type'          => 'integer',
        'sort_order'    => 'integer',
        'required'      => 'integer',
        'config'        => 'json',
        'option_jumps'  => 'json',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
