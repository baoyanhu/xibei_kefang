# 基础配置—样式配置实现方案

> 状态：已定

## 需求范围

- 新增 B 端样式配置详情接口。
- 新增 B 端样式配置保存接口。
- 配置项只包含主题色、按钮文字颜色和 Banner URL。
- Banner 由调用方传入已上传完成的 HTTP/HTTPS URL，本模块不处理文件上传。
- 不修改任何数据表或字段，不修改 Vue 端。

## 数据来源

使用 `merchant_configs` 表作为商户基础样式配置的唯一数据源：

- 查询条件：`merchant_id + config_type=style`。
- 数据库唯一索引：`uk_mc_merchant_type (merchant_id, config_type)`。
- `config_payload.theme` 对应接口字段 `theme_color`。
- `config_payload.primaryBtnText` 对应接口字段 `button_text_color`。
- `config_payload.banner` 对应接口字段 `banner_url`。

`styles` 表是问卷通过 `questionnaires.style_id` 引用的可复用样式模板，本次不读写该表。

## 接口设计

### 1. 查询样式配置

- 路由：`POST /admin/style/detail`
- 入参：`merchant_id`（必填，正整数）
- 逻辑：校验商户存在后查询 `config_type=style` 的配置。
- 未保存配置时返回空字符串字段，便于前端首次录入。

### 2. 保存样式配置

- 路由：`POST /admin/style/save`
- 入参：`merchant_id`、`theme_color`、`button_text_color`、`banner_url`。
- 颜色值必须符合 `#RRGGBB`。
- Banner 必须是完整的 HTTP/HTTPS URL。
- 数据已存在时更新，不存在时新增。
- 配置写入与 `audit_logs` 审计记录放在同一数据库事务中。
- 审计记录保存修改前、修改后和商户 ID。

## 技术分层

- Controller：参数接收、调用 Service、三段式 `save_log`、`returnJson`。
- Service：业务校验、数据映射、upsert 和审计事务。
- Model：表名、字段白名单、类型转换和自动时间戳。
- Route：两个业务接口均为 POST，并挂载 `AdminSign`。

## 待解项

- 无。

## 变更记录

| 日期 | 状态 | 变更内容 |
|---|---|---|
| 2026-08-17 | 已定 | 根据用户确认新建样式配置详情与保存接口实现方案。 |
