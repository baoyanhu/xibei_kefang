# 问卷列表模块实现方案

> 状态：已实现(2026-08-19,集成测试 41/41 通过)
> 原型:https://survey-admin.xibeitest.com/questionnaires(2026-08-19 逐项点击勘察)

## 一、原型功能清单(勘察结论)

列表页:
1. 筛选:问卷名称(模糊)、更新时间(起止日期)、操作人;查询/重置
2. 分页列表:ID / 问卷名称 / 状态 / 题目数 / 更新时间 / 操作人 / 操作;10 条每页;总数展示
3. 操作列(按状态变化):启用卷→详情/编辑/复制/禁用/删除;禁用或草稿卷→详情/编辑/复制/启用/删除
4. 详情:弹窗展示基本信息 + 题目列表(题型/标题/选项/NPS 规则/维度)
5. 新增问卷:独立编辑页(temp ID),默认 1 道单选题,「创建问卷」提交
6. 编辑问卷:独立编辑页整卷编辑,「保存问卷」提交
7. 复制:生成「原名称 · 副本」,深拷贝题目选项
8. 启用:确认弹窗,提示「启用后该商户其他问卷将自动禁用」(同商户互斥)
9. 禁用:启用卷专用
10. 删除:确认弹窗,「删除后不可恢复」
11. 页面提示:每个品牌(商户)只能启用一个问卷

编辑器(新增/编辑共用):
- 7 种题型:1单选 / 2多选 / 3NPS / 4维度 / 5图片 / 6文本 / 7菜品
- 通用:题目标题(≤40 字)、是否必填开关、拖动排序
- 单选/多选:选项(≤20 字)增删排序;选项级「补充说明」开关(短文/图片两种勾选);选项级跳题(目标:继续下一题 none / 任意后续题 / 感谢页 thanks)
- NPS:分值范围(最低分—最高分,默认 1—5,上限 10);每分值可配图标(默认数字,可传图 ≤500KB,可恢复默认);分值跳题(按分值);低分补充配置(低于 X 分时:允许图片+必填+最多 N 张 jpg/png≤5MB;允许文字+必填+最多 N 字)
- 维度:维度名称列表增删排序(如口味/辣度);5 星独立配置;按星数跳题
- 菜品:「选择菜品」弹窗(商品编码/商品名称/SKU 编码/SKU 名称/第三方编码/规格单位,搜索+分页,数据来自 RMS 菜品中心);配置方式二选一:评分配置(分值范围+低分触发图片/文字)或维度配置(维度列表+5 星+维度平均分低于阈值触发)
- 图片:最多上传 N 张(jpg/png 单张 ≤500KB)
- 文本:短文(单行)/长文(多行);最多 N 字(默认 200);允许上传图片勾选

## 二、数据来源(全部为既有表,不改表)

| 表 | 用途 | 关键点 |
|---|---|---|
| questionnaires | 问卷主表 | status: draft/disabled/enabled;active_flag 同商户唯一启用;delete_time 软删除;name≤128(原型限 40) |
| question_groups | 题目组 | uk(questionnaire_id,sort_order);B 端编辑器无组概念 → 每卷固定一个默认组,题目全挂默认组 |
| questions | 题目 | type 1-7;config JSON 存题型配置;option_jumps JSON 存跳题(按选项/分值/星数索引,元素 none/数字qid/thanks) |
| question_options | 选项 | 仅单选/多选落地记录;uk(question_id,value);NPS/维度选项不落表(由 config 推导) |
| nps_score_images | NPS 分值图标 | uk(question_id,score);image_url 存 dataURL 或 http URL(≤500KB),随 save 全量替换 |
| users | 操作人显示 | 列表 LEFT JOIN users.name;无关联显示 `--` |

库内 config 契约(已按存量数据核对):
- type=1/2: `{options_extras:[null|{text:bool,image:bool},...]}`
- type=3: `{upper_bound, nps_low_threshold, nps_enable_image, nps_image_limit, nps_require_image, nps_enable_text, nps_text_limit, nps_require_text}`
- type=4: `{dimensions:[{name}], star_slots:["★"...]}`
- type=5: `{max_images}`
- type=6: `{required, text_mode:short|long, max_chars, enable_image, image_limit, require_image}`
- type=7: `{dish_config_mode:rating|dimension, dish_skus:[{goods_code,goods_name}], dish_score_max, dish_low_threshold, dish_enable_image, dish_image_limit, dish_require_image, dish_enable_text, dish_text_limit, dish_require_text, dimensions, star_slots, dish_dim_low_threshold}`

## 三、接口设计(6 个,全 POST + AdminSign)

路由前缀用 `admin/survey/*`:基础配置已占用 `admin/questionnaire/detail|save`(merchant_configs 商户级配置),同路径不可复用;survey 对应问卷业务主体,与 C 端 survey_instances 命名一致。

### 1. POST admin/survey/list — 分页列表
- 入参:merchant_id(必填)、name(模糊)、update_time_start / update_time_end(Y-m-d,闭区间)、operator(模糊匹配 users.name)、page(默认1)、page_size(默认10)
- 逻辑:软删排除;题目数 COUNT(questions JOIN question_groups);操作人 LEFT JOIN users
- 返回:total / list[{id, name, status, question_count, update_time, operator}]

### 2. POST admin/survey/detail — 问卷详情(详情弹窗 + 编辑页回显共用)
- 入参:merchant_id、id
- 返回:基本信息{id, name, status, active_flag, update_time, operator, question_count} + questions[{id, type, title, required, sort_order, config, option_jumps, options[{id,label,value,sort_order}], nps_icons[{score,image_url}]}]

### 3. POST admin/survey/save — 保存(新增/编辑二合一,整卷全量替换)
- 入参:merchant_id、id(编辑时传,新增不传)、name(必填≤40)、operator_id(可选)、questions[{type,title,required,sort_order,config,option_jumps,options[],nps_icons[]}]
- 校验:题目 1-50 道;type∈1-7;标题必填≤40;跳题目标仅允许后续题或 thanks;NPS 图标 ≤500KB;菜品题校验 dish_skus 非空
- 逻辑:事务内——新增:插 questionnaire(draft) + 默认组 + 题目 + 选项 + NPS 图标;编辑:更新主表,题目/选项/NPS 图标全删全插(物理删除,子表无软删字段);审计 before/after
- 返回:问卷 id

### 4. POST admin/survey/copy — 复制
- 入参:merchant_id、id、operator_id(可选)
- 逻辑:事务内深拷贝主表(name = 原名 + ` · 副本`,status=draft,active_flag=0)+ 默认组 + 题目 + 选项 + NPS 图标;审计
- 返回:新问卷 id

### 5. POST admin/survey/status — 启用/禁用
- 入参:merchant_id、id、action(enable|disable)、operator_id(可选)
- enable 逻辑:事务内锁问卷 → 校验存在且未删除 → 同商户其他 active_flag=1 卷置 active_flag=0+status=disabled → 自身 active_flag=1+status=enabled;审计
- disable 逻辑:自身 active_flag=0+status=disabled;审计
- 返回:更新后的 status

### 6. POST admin/survey/delete — 删除(软删除)
- 入参:merchant_id、id、operator_id(可选)
- 逻辑:启用中的问卷禁止删除(先禁用);置 delete_time=NOW();题目/选项子表数据保留(子表无软删字段,保留便于恢复,孤儿数据无害——查询永远经问卷主表关联)
- 返回:成功

## 四、技术分层

- Controller `app/controller/admin/Survey.php`:参数接收、调 Service、三段式 save_log、returnJson;构造器注入 SurveyService
- Service `app/service/admin/SurveyService.php`:校验/组装/事务/审计;构造器注入各 Model(TP8 Model 静态代理即可满足时按既有代码风格用静态代理,注入不强制)
- Model 新建 5 个:QuestionnaireModel / QuestionGroupModel / QuestionModel / QuestionOptionModel / NpsScoreImageModel(显式 $name + $field 白名单 + $type json + 时间戳);UserModel 若仅列表 JOIN 用 Db 查询则不建
- Route:admin 分组追加 6 条 POST,全挂 AdminSign
- 审计:AuditLogModel 追加 ACTION_SAVE_SURVEY / ACTION_COPY_SURVEY / ACTION_SURVEY_STATUS / ACTION_DELETE_SURVEY 等常量

## 五、与既有代码的边界

- 不动 `admin/questionnaire/*`(基础配置·问卷配置,merchant_configs 数据源)
- 不动 `admin/style/*`、`admin/dish/*`、`admin/store/*`
- 不动 vue/ 端任何文件(前后端隔离)
- 不修改任何表结构(方案零 DDL)

## 六、待解项(需用户确认)

1. **路由前缀** `admin/survey/*` 是否接受(因 questionnaire 路径已被基础配置占用)
2. **删除策略**:问卷软删 + 子表保留(本方案);如需子表物理删除请指出
3. **操作人**:入参可选 operator_id(users.id),不传则 updated_by 置 NULL、列表显示 `--`;B 端页面接入真实登录态后由前端传入
4. **菜品选择弹窗数据源**:本次只做问卷保存(dish_skus 全量提交);「选择菜品」弹窗的菜品搜索接口(RMS 拉取)是否本次一起做,还是单独任务

## 变更记录

| 日期 | 状态 | 变更内容 |
|---|---|---|
| 2026-08-19 | 待确认 | 原型勘察完毕,新建问卷列表模块实现方案(6 接口) |
