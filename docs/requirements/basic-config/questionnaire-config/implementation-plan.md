# 基础配置—问卷配置实现方案

> 状态：已定（用户确认 merchant_configs 单表方案）

## 需求范围

- 新增 B 端问卷配置详情接口。
- 新增 B 端问卷配置保存接口。
- 配置项覆盖页面元素：触发时机（立即/延迟推送）、延迟时长（分钟）、奖励形态（积分开关+数量 / 券开关+CRM 券模板 ID）。
- 券模板为微生活侧数据，本模块仅存模板 ID，不提供模板列表查询。
- 不修改任何数据表或字段，不修改 Vue 端。

## 数据来源

使用 `merchant_configs` 表作为问卷配置的唯一数据源（与样式配置同构）：

- 查询条件：`merchant_id + config_type=reward`。
- 数据库唯一索引：`uk_mc_merchant_type (merchant_id, config_type)`。
- `config_payload` JSON 键与接口字段映射：

| payload 键 | 接口字段 | 说明 |
|---|---|---|
| triggerMode | trigger_mode | 1=立即推送 / 2=延迟推送 |
| delayMinutes | delay_minutes | 延迟分钟数；仅 trigger_mode=2 必填，1-1440（最长 24 小时） |
| rewardPoints | reward_points | 积分开关 0/1 |
| points | points | 积分数量；仅 reward_points=1 必填，正整数 |
| rewardCoupon | reward_coupon | 券开关 0/1（可与积分同时启用） |
| couponTemplateId | coupon_template_id | 微生活券模板 ID；仅 reward_coupon=1 必填，非空字符串 |

- 库内已有一行 seed 数据（merchant_id=1, config_type=reward）证明 payload 结构。

## 接口设计

### 1. 查询问卷配置

- 路由：`POST /admin/questionnaire/detail`
- 入参：`merchant_id`（必填，正整数）
- 逻辑：校验商户存在后查询 `config_type=reward` 的配置。
- 未保存配置时返回默认值（trigger_mode=1、开关全 0、数值字段 0/空串），便于前端首次录入。

### 2. 保存问卷配置

- 路由：`POST /admin/questionnaire/save`
- 入参：`merchant_id`、`trigger_mode`、`delay_minutes`、`reward_points`、`points`、`reward_coupon`、`coupon_template_id`。
- 校验规则：
  - trigger_mode 只能 1/2；=2 时 delay_minutes 必填且 1-1440，=1 时 delay_minutes 强制置空（null）。
  - reward_points 只能 0/1；=1 时 points 必填正整数；=0 时 points 强制置 0。
  - reward_coupon 只能 0/1；=1 时 coupon_template_id 必填非空；=0 时强制置空串。
  - 两种奖励可同时启用或同时关闭（不发奖励，仅收集评价）。
- 数据已存在时更新，不存在时新增（upsert）。
- 配置写入与 `audit_logs` 审计记录放在同一数据库事务中，审计含 before/after 快照。

## 技术分层

- Controller：`app/controller/admin/Questionnaire.php`，detailOp + saveOp，三段式 save_log + returnJson，构造器注入 Service。
- Service：`app/service/admin/QuestionnaireService.php`，校验拆小方法 + 闭包事务 + 商户/配置行锁 + 审计。
- Model：复用 `MerchantConfigModel`（新增 `TYPE_REWARD` 常量）与 `AuditLogModel`（新增问卷配置审计常量），不新建 Model。
- Route：两个业务接口均为 POST，挂载 `AdminSign` 验签中间件。

## 影响面

- `MerchantConfigModel` 新增常量：纯增量，现有 style 读写不受影响。
- `AuditLogModel` 新增两个常量：纯增量。
- 新路由 `admin/questionnaire/*` 与现有路由无冲突。
- 不动 `vue/**`、不动 C 端代码。

## 待解项

- 无。

## 变更记录

| 日期 | 状态 | 变更内容 |
|---|---|---|
| 2026-08-18 | 已定 | 根据用户页面描述与 merchant_configs seed 数据确认单表 JSON 方案。 |
