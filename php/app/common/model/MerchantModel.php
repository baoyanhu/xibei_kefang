<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 商户主数据模型，用于校验基础配置归属的商户。
 */
class MerchantModel extends Model
{
    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'merchants';

    /** 允许写入的字段白名单。 */
    protected $field = [
        'id',
        'name',
        'logo_url',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射。 */
    protected $type = [
        'id' => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
