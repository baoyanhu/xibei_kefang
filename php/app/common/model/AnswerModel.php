<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 顾客答卷模型，payload 为提交时自包含快照（题目/题型/答案/补充说明）。
 */
class AnswerModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'answers';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'instance_id',
        'order_no',
        'payload',
        'multi_dim_avg',
        'nps_avg',
        'submitted_at',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射，答卷快照 JSON 在模型层自动编解码。 */
    protected $type = [
        'id'           => 'integer',
        'instance_id'  => 'integer',
        'payload'      => 'json',
        'multi_dim_avg' => 'float',
        'nps_avg'      => 'float',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
