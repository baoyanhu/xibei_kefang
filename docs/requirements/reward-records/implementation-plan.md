# 评价发放记录(B 端)实现方案

> 状态:**已实现**(2026-08-19,集成测试 27/27 通过,零数据污染)
> 原型:`https://survey-admin.xibeitest.com/reward-records`(页面实测 + 组件源码 RewardRecordListView 已勘察)
> 日期:2026-08-19

## 1. 页面功能清点(原型实测 + 组件源码)

| 元素 | 原型行为 |
|---|---|
| 筛选-订单号 | 文本输入,模糊 |
| 筛选-卡号 | 文本输入,模糊 |
| 筛选-券模板 ID | 文本输入,模糊 |
| 筛选-券号 | 文本输入,模糊 |
| 筛选-激励类型 | 下拉:全部/积分(=1)/券(=2) |
| 筛选-发放状态 | 下拉:全部/待发放(=1)/发放中(=2)/已发放(=3)/发放失败(=4)/已人工补发(=5) |
| 查询/重置 | 重置清空回第 1 页 |
| 表格 12 列 | 记录 ID / 实例 ID / 订单号 / 卡号 / 类型 / 内容 / 券号 / 状态 / 流水号 / 创建时间 / 发放时间 / 失败原因 |
| 行操作 | **无**(纯查询页;无导出按钮) |
| 分页 | 10/20/50,共 N 条 |

列渲染规则(组件源码):
- **类型**:reward_type 1=积分 / 2=券
- **内容**:reward_type=2 → `模板 {coupon_template_id}`;reward_type=1 → `{points} 积分`(前端用 points/coupon_template_id 原始字段自行拼装)
- **状态**:{1:待发放/info, 2:发放中/primary, 3:已发放/success, 4:发放失败/danger, 5:已人工补发/success}(tag 颜色由前端渲染,后端只给值)
- 行数据来自 `e.instance?.order_no` / `e.instance?.member_card_no` → **后端必须 join survey_instances 补订单号/卡号**

## 2. 数据来源(零 DDL)

- 主表 `reward_records`(21 条,已逐列复核):id / instance_id / reward_type / points / coupon_template_id / status / grant_serial_no / coupon_no / granted_at / failure_reason / retry_count / create_time / update_time;唯一键 uk_rr_instance_type(instance_id, reward_type)(一个实例每类激励至多一条)
- 关联 `survey_instances`:order_no、member_card_no(**leftJoin**——沿用顾客评价模块孤儿数据教训,实例被清理的发放记录仍要展示,关联字段给 '--')
- 现有数据分布 (reward_type,status):(1,1)=1、(1,3)=9、(1,4)=1、(2,1)=1、(2,3)=9;状态 2/5 暂无数据(枚举仍全支持)
- 本模块**纯查询**:不改状态、不补发、不审计(输出已脱敏)

## 3. 接口设计(1 个)

### B16 `POST /admin/reward/list` — 评价发放记录列表

**入参**(全部可选,除分页):

| 参数 | 说明 |
|---|---|
| page / page_size | 默认 1 / 10,page_size 最大 100(分页器 10/20/50) |
| order_no | 订单号模糊(si.order_name LIKE,按库内明文) |
| member_card_no | 卡号模糊(按库内明文卡号匹配,同 B13 口径) |
| coupon_template_id | 券模板 ID 模糊(rr.coupon_template_id LIKE,varchar) |
| coupon_no | 券号模糊(rr.coupon_no LIKE) |
| reward_type | 枚举校验:1=积分 2=券,其他值 → 250 |
| status | 枚举校验:1-5,其他值 → 250 |

**出参**:`{count, list}`,按 create_time 倒序、同秒按 id 倒序:

```json
{"id":7,"instance_id":21,"order_no":"MANUAL-COUPON-168114125668",
 "member_card_no":"168****2566","reward_type":2,"reward_type_text":"券",
 "points":null,"coupon_template_id":"100426540","coupon_no":"168114125668",
 "status":3,"status_text":"已发放","grant_serial_no":"GRANT-xxx",
 "create_time":"2026-06-27 16:35:20","granted_at":"2026-06-27 16:35:20",
 "failure_reason":null}
```

- `member_card_no` 出参脱敏:前 3 后 4(如 `888****0001`),实例缺失时 '--'
- `order_no` 实例缺失时 '--';`points`/`coupon_template_id`/`coupon_no`/`grant_serial_no`/`granted_at`/`failure_reason` 可空字段原样(null)
- 风格对齐 B13:枚举值 + `*_text` 伴随字段,内容列由前端用 points/coupon_template_id 自行拼装

## 4. 实现文件

| 文件 | 动作 | 内容 |
|---|---|---|
| `php/app/common/model/RewardRecordModel.php` | 新增 | 常量 REWARD_TYPE_POINTS=1/COUPON=2 + STATUS_* 1-5 + $REWARD_TYPE_TEXTS/$STATUS_TEXTS + $name='reward_records' + 字段白名单 + $type |
| `php/app/service/admin/RewardRecordService.php` | 新增 | getList(leftJoin 查询+闭包筛选+分页+组装)+ validateListInput + maskCardNo(Service 内重写同款 5 行,不跨 Service 抽公共) |
| `php/app/controller/admin/RewardRecord.php` | 新增 | listOp,四件事+构造器注入+三段 save_log |
| `php/route/app.php` | +1 路由 | `admin/reward/list` + AdminSign |
| `docs/api/customer-survey.html` | 追加 | B16 区块 |
| `docs/todos/2026-08-19.md` | 追加 | 任务四清单 |

**不改动**:基础配置/问卷/推送/顾客评价模块、vue/ 端、表结构(零 DDL)。

## 5. 测试计划

真实签名 + AES 通道集成测试(预期值全部由脚本内 PDO 直查动态生成,不经 MCP 手工预估):

1. 全量 list:count=21、默认 10 条、create_time 倒序、12 列字段齐全、类型/状态 text 正确
2. 逐个筛选:order_no / member_card_no / coupon_template_id / coupon_no 模糊命中数与 PDO 一致
3. 枚举筛选:reward_type=1 与 =2、status=3 与 =4(覆盖失败原因非空行)
4. 组合筛选:reward_type=2 + status=3
5. 卡号脱敏:前 3 后 4
6. 分页:page_size=5 时页数/边界正确
7. 非法参数:reward_type=3 → 250;status=9 → 250;page_size=200 → 按上限 100
8. 纯查询零写入:测后 MCP 复核 reward_records 仍 21 行、无残留

## 6. 风险与说明

- **人工补发不在本期**:原型 api 模块里有 `manualGrant: POST /admin/rewards/{id}/manual-grant`,但该页面**没有任何按钮触发它**(状态 4 行也没有补发操作),本期只做查询;若后续要做「人工补发」属写操作,另开任务(需事务+幂等+审计)
- **实例删除的孤儿记录**:leftJoin 保证展示,关联列给 '--';现有 21 条全部能关联上,该分支是防御性设计
- **retry_count 不输出**:原型 12 列不含它,出参不加(前端用不到的字段不暴露)
- 原型 BFF 端点 `/b/reward-records`,我们按项目规范走 `admin/reward/list`,联调以前端改指向为准(与前三个模块同理)
