# 基础配置—菜品配置实现方案

> 状态：**待确认**（用户确认后开始写代码）

## 需求范围

- 新增 B 端菜品配置详情接口。
- 新增 B 端菜品配置保存接口。
- 配置项只有一个开关：是否关联菜品数据（启用/禁用）。
- 不修改任何数据表或字段，不修改 Vue 端。

## 数据来源

使用 `merchant_configs` 表作为菜品配置的唯一数据源（与样式配置 style、问卷配置 reward 同构，基础配置三兄弟的最后一个）：

- 查询条件：`merchant_id + config_type=dish`。
- 数据库唯一索引：`uk_mc_merchant_type (merchant_id, config_type)`。
- payload 只有一个键：

| payload 键 | 接口字段 | 说明 |
|---|---|---|
| linkDishData | dish_link_enabled | 1=启用（关联菜品数据）/ 0=禁用 |

- 库内已有 seed 数据（merchant_id=1）：`{"linkDishData": true}`，证明结构与命名。

- 语义说明（对照 `questionnaires.dish_link_enabled` 表注释）：该开关控制「菜品」题型在问卷中的可用性，以及 CRM 菜品数据是否进入评价流程；菜品白名单明细（dish_whitelists 表）属于问卷题目组配置，不在本接口范围。

## 接口设计

### 1. 查询菜品配置

- 路由：`POST /admin/dish/detail`
- 入参：`merchant_id`（必填，正整数）
- 逻辑：校验商户存在后查询 `config_type=dish` 的配置。
- 未保存配置时返回默认值 `dish_link_enabled=0`（禁用），便于前端首次录入。

### 2. 保存菜品配置

- 路由：`POST /admin/dish/save`
- 入参：`merchant_id`、`dish_link_enabled`。
- 校验规则：
  - `dish_link_enabled` 只能为 0 或 1。
- 数据已存在时更新，不存在时新增（upsert）。
- 配置写入与 `audit_logs` 审计记录放在同一数据库事务中，审计含 before/after 快照。

## 技术分层（与 style/questionnaire 完全同构）

- Controller：`app/controller/admin/Dish.php`，detailOp + saveOp，三段式 save_log + returnJson，构造器注入 Service。
- Service：`app/service/admin/DishService.php`，getDetail + save，校验拆小方法 + 闭包事务 + 商户/配置行锁 + 审计。
- Model：复用 `MerchantConfigModel`（新增 `TYPE_DISH` 常量）与 `AuditLogModel`（新增 `ACTION_SAVE_DISH_CONFIG` / `TARGET_MERCHANT_DISH_CONFIG` 常量），不新建 Model。
- Route：两条 POST 路由挂 `AdminSign` 验签中间件。

## 待解项（默认值需要你拍板）

1. **未保存时的 detail 默认值**：方案按 `0=禁用` 返回（保守，不自动关联菜品）。若你希望默认启用改为 `1`。
2. 其余无。

## 影响面（预查结论）

- `MerchantConfigModel` 新增 `TYPE_DISH` 常量：纯增量，现有 style/reward 读写不受影响。
- `AuditLogModel` 新增两个常量：纯增量。
- 新路由 `admin/dish/*` 与现有 admin 路由（store/style/questionnaire）无冲突。
- 不动 `vue/**`、不动 C 端代码。

## 变更记录

| 日期 | 状态 | 变更内容 |
|---|---|---|
| 2026-08-18 | 待确认 | 根据用户需求（单开关两接口）新建菜品配置实现方案。 |
