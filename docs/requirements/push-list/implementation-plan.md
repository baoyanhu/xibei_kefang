# 评价推送列表(B 端)实现方案

> 状态:**已实现**(2026-08-19,集成测试 28/28 通过,零数据污染)
> 原型:`https://survey-admin.xibeitest.com/push-list`(组件源码已勘察)
> 日期:2026-08-19

## 1. 页面功能清点(原型实测)

纯查询页,**无任何写操作**(无重推/补发/导出按钮,表格无行操作列):

| 元素 | 原型行为 |
|---|---|
| 筛选-订单号 | 文本输入,模糊 |
| 筛选-用户名 | 文本输入(实际按会员卡号 `member_card_no` 模糊),模糊 |
| 筛选-手机号 | 文本输入,模糊 |
| 筛选-推送状态 | 下拉:已推送 / 待推送(默认全部) |
| 筛选-答题状态 | 下拉:已提交 / 已打开 / 未打开(默认全部) |
| 筛选-创建时间 | 日期范围(daterange,YYYY-MM-DD,开始/结束) |
| 查询/重置 | 重置清空全部条件回第 1 页 |
| 表格 10 列 | ID / 用户名 / 手机号 / 订单号 / 推送状态 / 推送时间 / 答题状态 / 答题时间 / 创建时间 / 菜品 |
| 分页 | 10/20/50 每页,共 N 条 |

### 状态映射(原型前端逻辑,后端返回原始 status 数字即可复用)

原型期望 `status`:1=待推送 → 推送[待推送]/答题[未推送];2=已推送 → 推送[已推送]/答题[未打开];3=已打开 → 推送[已推送]/答题[已打开];≥4 已提交 → 推送[已推送]/答题[已提交]。

与库表 `survey_instances.status` 注释完全一致(1=待推送 2=已推送 3=已打开 4=已提交 5=已失效 6=推送失败)。

## 2. 数据来源(零 DDL)

- 主表 `survey_instances`(48 条真实测试数据,status 1-5 均有):id / order_no / member_card_no / phone / status / pushed_at / opened_at / submitted_at / create_time
- 菜品列表 `survey_dishes`(118 条):按 instance_id 聚合 dish_name,按 sort_order 排序,返回**数组**(前端自己 `join('、')`)
- **脱敏现状**:库里 `phone` 已是脱敏格式(`138****0001`),`member_card_no` 为明文卡号(`88880001`)→ 列表输出时卡号按「前 3 后 4」脱敏,与 phone 视觉规则一致;手机号原样输出
- `raw_data` 列表页不展示(原型存了但未渲染),不返回,省流量

## 3. 接口设计(1 个)

### B13 `POST /admin/push/list` — 评价推送列表

AdminSign 验签(同既有 B 端接口),全 POST,biz_content 加密通道。

**入参**(全部可选,除分页):

| 参数 | 类型 | 说明 |
|---|---|---|
| page | int | 默认 1 |
| page_size | int | 默认 10(前端分页器 10/20/50) |
| order_no | string | 订单号模糊 |
| member_card_no | string | 会员卡号模糊(「用户名」框) |
| phone | string | 手机号模糊(按库内脱敏格式匹配,见 §5 风险) |
| push_state | string | `pushed`=已推送(status IN 2,3,4,5,6)/ `pending`=待推送(status=1) |
| answer_state | string | `submitted`(status=4)/ `opened`(status=3)/ `unopened`(status=2) |
| create_time_start | string | YYYY-MM-DD,闭区间起(补 00:00:00) |
| create_time_end | string | YYYY-MM-DD,闭区间止(补 23:59:59) |

> push_state 与 answer_state 同传时取交集(AND)。

**出参**:

```json
{
  "code": 0, "message": "成功",
  "data": {
    "count": 48,
    "list": [{
      "id": 1,
      "order_no": "E2E_003",
      "member_card_no": "888****0001",
      "phone": "138****0001",
      "status": 4,
      "status_text": "已提交",
      "pushed_at": "2026-06-20 13:36:52",
      "opened_at": "2026-06-20 13:36:52",
      "submitted_at": "2026-06-20 13:36:52",
      "create_time": "2026-06-20 13:36:52",
      "dishes": ["清蒸鲈鱼", "红烧肉", "宫保鸡丁"]
    }]
  }
}
```

排序:`create_time DESC, id DESC`。

## 4. 实现文件(对齐上一模块模式)

| 文件 | 动作 | 内容 |
|---|---|---|
| `php/app/common/model/SurveyInstanceModel.php` | 新增 | $name + 字段白名单 + status 常量(1-6)+ $type |
| `php/app/common/model/SurveyDishModel.php` | 新增 | $name='survey_dishes' + 白名单 |
| `php/app/service/admin/PushListService.php` | 新增 | getList:入参校验 → 筛选构汇(闭包 where)→ 分页 paginate → 批量聚合 dishes(fetch 组装,不逐行查)→ 脱敏 → 输出 |
| `php/app/controller/admin/PushList.php` | 新增 | listOp,三段 save_log,构造器 DI |
| `php/route/app.php` | 追加 | admin 组内 `Route::post('push/list', ...)` + AdminSign |
| `docs/api/customer-survey.html` | 追加 | B13 区块(响应字段表 + 请求示例 + curl + notes) |
| `docs/todos/2026-08-19.md` | 追加 | 任务二清单(已建,状态待确认) |

**不改动**:基础配置模块、问卷列表模块、vue/ 端、任何表结构(零 DDL)、不写审计(查询类且输出已脱敏;「查看不脱敏」属顾客评价详情接口,后续任务)。

## 5. 测试计划(纯查询,零数据污染)

集成测试(真实 MD5 签名 + AES 通道,同上一模块方式),用现有 48 条数据断言,不插不改不删:

1. 无条件:count=48、分页 page_size=10 首页 10 条、排序倒序
2. order_no 模糊(`E2E` 命中)、member_card_no 模糊、phone 模糊(脱敏格式)
3. push_state=pushed → count=47(26+7+12+2);pending → 1
4. answer_state=submitted(12)/ opened(7)/ unopened(26)
5. push_state + answer_state 交集(pushed+opened=7)
6. 时间范围:单日闭区间边界(00:00:00 / 23:59:59 各命中)
7. dishes 聚合:instance 1 返回 3 个菜品按 sort_order 排序
8. member_card_no 脱敏格式(`888****0001`)、phone 原样
9. 非法参数:push_state=xxx → 250 报错

## 6. 风险与说明

- **phone 全号搜不中**:库内已是脱敏格式,输入 `13800138001` 无法命中 `138****0001`,这是入库链路的既定形态;文档 notes 说明按脱敏格式搜索。若未来要求全号可搜,需入库侧改存明文+出参脱敏(涉及 C 端/推送链路任务,不在本次范围)
- **create_time 无索引**:现有索引只有 checkout_at/expire_at 等;当前量级(48 行)全表排序无压力,量级上来后补 `idx_si_create_time`(本次零 DDL 不动)
- **答题状态「未推送」**(:status=1 时原型显示)不需要后端单独枚举,status=1 前端自然映射
- 原型 BFF 端点为 `/b/surveys`(它们自己的网关),我们按项目规范走 `admin/push/list`,联调以前端改指向我们文档为准(与上一模块同理)
