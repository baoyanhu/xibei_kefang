<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 题目组模型，B 端编辑器无分组概念，题目统一挂问卷默认组。
 */
class QuestionGroupModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'question_groups';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'questionnaire_id',
        'name',
        'sort_order',
        'display_limit',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'              => 'integer',
        'questionnaire_id' => 'integer',
        'sort_order'      => 'integer',
        'display_limit'   => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
