<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 问卷实例命中菜品快照模型，按实例聚合展示菜品明细。
 */
class SurveyDishModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'survey_dishes';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'instance_id',
        'dish_id',
        'dish_name',
        'price',
        'question_group_id',
        'sort_order',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'               => 'integer',
        'instance_id'      => 'integer',
        'price'            => 'float',
        'question_group_id' => 'integer',
        'sort_order'       => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
