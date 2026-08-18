<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 商户基础配置模型，承载样式等按类型分组的 JSON 配置。
 */
class MerchantConfigModel extends Model
{
    /** 样式配置类型，对应 merchant_configs.config_type=style。 */
    public const TYPE_STYLE = 'style';

    /** 问卷配置类型，对应 merchant_configs.config_type=reward。 */
    public const TYPE_REWARD = 'reward';

    /** 菜品配置类型，对应 merchant_configs.config_type=dish。 */
    public const TYPE_DISH = 'dish';

    /** 逻辑表名，不包含数据库前缀。 */
    protected $name = 'merchant_configs';

    /** 允许写入的字段白名单，防止请求参数越权入库。 */
    protected $field = [
        'id',
        'merchant_id',
        'config_type',
        'config_payload',
        'updated_by',
        'create_time',
        'update_time',
    ];

    /** 数据库字段类型映射，JSON 配置在模型层自动编解码。 */
    protected $type = [
        'id'             => 'integer',
        'merchant_id'    => 'integer',
        'config_payload' => 'json',
        'updated_by'     => 'integer',
    ];

    /** 使用 DATETIME 秒精度自动维护创建和更新时间。 */
    protected $autoWriteTimestamp = 'datetime';

    /** 创建时间字段名。 */
    protected $createTime = 'create_time';

    /** 更新时间字段名。 */
    protected $updateTime = 'update_time';
}
