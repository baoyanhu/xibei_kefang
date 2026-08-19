<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * NPS 分值图标模型，每个 NPS 题目每个分值最多一张自定义图。
 */
class NpsScoreImageModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'nps_score_images';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'questionnaire_id',
        'question_id',
        'score',
        'image_url',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id'              => 'integer',
        'questionnaire_id' => 'integer',
        'question_id'     => 'integer',
        'score'           => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
