<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 题目选项模型，仅单选/多选题目落地选项记录。
 */
class QuestionOptionModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'question_options';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'question_id',
        'label',
        'value',
        'sort_order',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'          => 'integer',
        'question_id' => 'integer',
        'sort_order'  => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
