# 顾客评价(B 端)实现方案

> 状态:**已实现**(2026-08-19,集成测试 21/21 通过;**B16 导出经用户确认本期不做**,仅交付 B14/B15)
> 原型:`https://survey-admin.xibeitest.com/customer-answers`(组件源码已勘察)
> 日期:2026-08-19

## 1. 页面功能清点(原型实测 + 组件源码)

| 元素 | 原型行为 |
|---|---|
| 筛选-问卷名称 | 文本输入,模糊 |
| 筛选-提交时间 | 日期范围(daterange,YYYY-MM-DD) |
| 查询/重置 | 重置清空回第 1 页 |
| **导出按钮** | 按当前筛选导出 CSV(文件名 `顾客评价_YYYY-MM-DD.csv`) |
| 表格 8 列 | 答卷ID / 提交时间 / 订单号 / 问卷ID / 问卷名称 / 作答详情(前 3 题摘要+等 N 题) / 顾客 / 操作 |
| 操作-查看明细 | 右侧抽屉:答卷ID/提交时间/订单号/问卷/顾客 + 全部题目逐题展示 |
| 分页 | 10/20/50,共 N 条 |

抽屉按题型渲染:type4 维度题逐维度名+分(需 `config.dimensions`);type7 菜品题逐 `goods_code:分`(需 `dish_name_map` 映射菜名)+ 补充说明(followups: comment+photos);type5 图片题直接渲染 URL 数组;其余题型文本化 value。

## 2. 关键发现:payload 自包含(方案成立的基础)

`answers.payload` 由 C 端提交时写入,实测样本(答卷 39)结构:

```json
{ "questions": [
  {"question_id":281,"question_type":1,"title":"单选-满意度","value":"满意"},
  {"question_id":284,"question_type":4,"title":"维度-各维度打分","value":{"口味":5,"服务":3}},
  {"question_id":285,"question_type":5,"title":"图片-上传用餐图片","value":["http://static...png"]},
  {"question_id":287,"question_type":7,"title":"菜品-菜品评分",
   "value":{"1000010020000287":5},
   "followups":{"1000010020000288":{"comment":"...","photos":["http://..."]}}}
]}
```

title / question_type / value / followups 全部冗余在 payload 里 → **列表与详情的主体数据零拼装透传**,后端只需补:问卷名称(instance→questionnaire)、顾客卡号(instance,脱敏)、detail 的 type4 config(questions 表)与 dish_name_map(survey_dishes 表)。

`answer_images` 表另有 4 条图片记录,但原型只渲染 payload 内 URL,该表不参与本模块(C 端写入侧的冗余,不动)。

## 3. 数据来源(零 DDL)

- 主表 `answers`(12 条):id / instance_id / order_no / payload / submitted_at
- 关联 `survey_instances`:member_card_no(脱敏)、questionnaire_id
- 关联 `questionnaires`:name(问卷名称筛选+展示)
- detail 追加 `questions.config`(type4 dimensions,按 payload 里 question_id 批量取)
- detail 追加 `survey_dishes`(instance 的菜品快照 → `{goods_code: dish_name}` 映射)

## 4. 接口设计(3 个)

### B14 `POST /admin/answer/list` — 顾客评价列表

**入参**(可选,除分页):`page`/`page_size`(默认 10,最大 100)、`questionnaire_name`(模糊,questionnaires.name)、`submit_time_start`/`submit_time_end`(YYYY-MM-DD 闭区间,对应原型 from/to)。

**出参**:`{count, list}`,list 按提交时间倒序:

```json
{"answer_id":39,"submitted_at":"2026-07-14 10:00:00","order_no":"E2E-39",
 "questionnaire_id":35,"questionnaire_name":"E2E自测全题型问卷",
 "member_card_no":"888****0001","questions":[...payload.questions 原样透传...]}
```

### B15 `POST /admin/answer/detail` — 答题明细

入参:`answer_id`(必填)。出参 = B14 单条结构 + 每题补 `config`(type4 的 dimensions 等) + 顶层 `dish_name_map`(`{"1000010020000287":"清蒸鲈鱼"}`,survey_dishes 按 sort_order)。

### B16 `POST /admin/answer/export` — 导出 CSV(带审计)

入参 = B14 筛选(不含分页,全量导出)。出参:`{filename, content_base64}`(CSV UTF-8 带 BOM,前端 atob→Blob 下载,与原型文件名口径一致)。CSV 列:答卷ID/提交时间/订单号/问卷ID/问卷名称/顾客(脱敏)/作答详情(每题`标题:回答`分号拼接,图片题→`N 张图片`,菜品题→`N 道菜评分`)。

**审计**:导出写 `audit_logs`(action=`export_answer`,target_type=`answer`,payload 记筛选条件+导出行数+操作人)——对齐表注释「导出…均须留痕」。列表/详情输出已脱敏,不审计;不提供 unmasked(需配审计的「不脱敏查看」不在本期)。

## 5. 实现文件

| 文件 | 动作 | 内容 |
|---|---|---|
| `php/app/common/model/AnswerModel.php` | 新增 | $name='answers' + 白名单 + $type(payload=json) |
| `php/app/common/model/AuditLogModel.php` | +2 常量 | ACTION_EXPORT_ANSWER / TARGET_ANSWER |
| `php/app/service/admin/CustomerAnswerService.php` | 新增 | getList / getDetail / export;复用 SurveyInstanceModel·QuestionnaireModel·QuestionModel·SurveyDishModel |
| `php/app/controller/admin/CustomerAnswer.php` | 新增 | listOp / detailOp / exportOp,三段 save_log,构造器注入 |
| `php/route/app.php` | +3 路由 | admin/answer/{list,detail,export} + AdminSign |
| `docs/api/customer-survey.html` | 追加 | B14-B16 区块 |
| `docs/todos/2026-08-19.md` | 追加 | 任务三清单 |

复用上一模块的 `maskCardNo` 逻辑(在 PushListService 里是 private——本模块 Service 内重写同款 5 行,不跨 Service 抽公共,遵守反过度封装;若后续第三处再用,再抽 helper)。

**不改动**:基础配置/问卷列表/推送列表模块、vue/ 端、表结构(零 DDL)。

## 6. 测试计划

真实签名+AES 通道集成测试:

1. list:全量 count=12、倒序、字段齐全、questions 透传与库内 payload 一致、卡号脱敏
2. questionnaire_name 模糊命中、时间闭区间边界
3. detail:config 补齐(type4 有 dimensions)、dish_name_map 与 survey_dishes 一致、followups 原样
4. export:base64 可解码、CSV 行数=筛选数、表头正确、BOM 存在
5. 非法参数:缺 answer_id→250、非法日期→250
6. **预期值全部由测试脚本内 PDO 直查动态生成**(吸取 B13 教训:不经 MCP 手工预估,避免时区/样本误判)
7. export 会产生 1 条审计 → 测试后物理清理该审计行,MCP 复核 answers=12/instances=48/审计恢复

## 7. 风险与说明

- **问卷改名/删题影响历史答卷**:payload 是提交时快照,问卷后续编辑不影响历史展示(这正是快照设计意图);detail 的 config 按 question_id 反查 questions 表,若题目被删(问卷编辑是全删全建,question_id 会变)则 config 缺失 → 前端已有兜底(无 config 时按 value 键值对直接渲染),不报错
- **dish_name_map 同理**:survey_dishes 是实例时快照,稳定
- **导出量级**:本期单商户数据量小,同步生成 CSV 返回;量级上来后改异步任务+FTP(走既有 exports 机制,另行任务)
- 原型 BFF 端点 `/b/answers*`,我们按项目规范走 `admin/answer/*`,联调以前端改指向为准(与前两模块同理)
