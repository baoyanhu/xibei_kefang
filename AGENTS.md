# AGENTS.md — 项目规范强制分发

> 本文件由 init-project 技能生成。规范细节在子技能里,本文件做**路径强制分发与全局工作流对齐**。
>
> 📖 **全局通用工作流**：任务执行前必须严格遵循 [docs/AGENTS_GLOBAL.md](docs/AGENTS_GLOBAL.md) 规定的中文语言偏好、`docs/todos/` 任务清单确认流程、影响面检查、原型驱动开发 SOP 与完成报告规范。

## 项目结构

- `docs/`  文档(API 文档 / PRD / 设计稿 / 全局工作流规范)
- `php/`   后端(ThinkPHP 8)
- `vue/`   前端(完整脚手架:Vue3 + Vite + TS + Vant4,框架能力已全配)
- `vue/AGENTS.md`  前端项目内部规范(从 templates/vue-agents.md 复制,可在项目内改)

---

## ⚠️ 强制约束(违反即打回)

### ❶ php/ 目录强制使用 php-code-style 技能

**所有 `php/**/*.php` 和 `php/**/*.sql` 文件编辑前,AI 必须先加载 php-code-style 技能**。

加载方式: `load_skills=["php-code-style"]` 或在 php/ 子目录工作时自动触发。

php-code-style 提供的规范(违反即违规):
- 三层架构 Controller → Service → Model(禁跨层)
- Service 构造器注入 DI(禁 `static::instance()` 单例)
- Model 静态代理 `Model::where()`(禁 `instance()` / 禁硬编码表名)
- save_log 7 级日志(禁 `Log::` / `error_log` / `var_dump`)
- 全 POST 接口(禁 GET/PUT/DELETE 业务接口)
- DB schema:InnoDB + utf8mb4 + DATETIME 秒精度 + 无外键 + COMMENT 全覆盖
- 完整规范见 `.opencode/skills/php-code-style/SKILL.md`

### ❷ vue/ 目录强制使用 h5-code-style + html2vue 两个技能

**所有 `vue/**/*.vue`、`vue/**/*.ts`、`vue/**/*.tsx`、`vue/**/*.scss`、`vue/**/*.css` 文件编辑前,AI 必须先加载对应技能**。

#### 写前端代码 → 加载 h5-code-style

加载方式: `load_skills=["h5-code-style"]` 或在 vue/ 子目录工作时自动触发。

h5-code-style 提供的规范(违反即违规):
- H5 端代码标准唯一真相源(v3.3 / 12 章)
- 技术栈: Vue 3 `<script setup lang=ts>` + Vant4 + Pinia + vue-router + Vite + vite-plugin-mock + ECharts + pnpm
- 核心铁律:反过度工程化就地写不封装 + 项目硬栅栏(只动 api/·router/modules/·views/ 三目录) + HTML 原型 1:1 还原 + 组件三档(Vant 原生 → 基于 Vant 封装 → 纯自建)
- 类型策略:接口层零类型统一 postData + 业务层全面放开 any,禁用 ts-ignore/ts-expect-error 注释
- 鉴权:code 200/403/500,403 跳 403.vue,token 走 utils/token.ts
- 三态硬规则:有接口必走 AppSkeleton 全局骨架 / 数据空必用 AppEmpty / 禁手写 loading 空态(含裸用 van-skeleton/van-empty)
- 适配:vw, postcss-px-to-viewport 编译时转,750px 稿,class 写 px / inline 用 vw()
- 命名:项目/页面/文件 camelCase,组件 PascalCase,路由 name PascalCase+项目前缀 / path kebab-case+项目前缀,函数 handleXxx
- SFC 三段顺序:template→script setup→style scoped(Prettier vueIndentScriptAndStyle)
- 样式:CSS 变量优先禁裸色值,base→tokens→theme 三段 import + tokens/theme 缺样式就补进直接用 + Token 落点判定表,z-index 分层,scoped+:deep 穿透 Vant
- Mock:.mock.ts 后缀 + ts-nocheck 指令 + 全 post + URL 带 /api + 数据 1:1 誊抄
- 提交门禁:eslint --max-warnings=0 + prettier + husky + lint-staged
- 完整规范见 `.opencode/skills/h5-code-style/SKILL.md`

#### HTML 原型转 Vue 任务(触发词「html 转 vue」等)→ 加载 html2vue

加载方式: `load_skills=["html2vue"]` 或检测到「html 生成 vue」「还原原型」「html 转 vue」意图时自动触发。

html2vue 提供的规范(违反即违规):
- **两条铁律(硬要求,优先级高于一切)**:
  1. **1:1 还原**:原型是底稿要誊抄,不是灵感要重做。样式数值/交互行为/DOM 结构/文案/数据,一律照抄——不发挥、不优化、不美化、不增减
  2. **遵守两个 md 的每一条**:`vue/AGENTS.md`(端宪法)+ `.opencode/skills/h5-code-style/SKILL.md`(代码标准,init-project 已装配)是硬约束,生成前必须 read,生成中每一条都照办
- 启动流程:确认端 + read 三份(vue/AGENTS.md + h5-code-style SKILL.md + 原型目录) + 复述关键约束;原型原样落档 `docs/proto/<project>/`(gitignore,不提交),全程以存档为 1:1 真值源,禁凭记忆比对
- 原型清点:文件信息/图片图标/图表清单/设计稿宽度/screen 清单/角色清单/状态清单
- 逐元素视觉对照 + 逐交互点对照(触发→反馈→结果三段)+ 逐屏 1:1 终验(产物截图 vs proto 存档并排比对,差异修完再截)
- 已踩过的坑——样式类:抽组件包装图片、语义化配色(正向绿/负向红;原型语义色也别抹灰)、编造 mock 数据、字号硬套 token 阶梯、width/height 属性定尺寸、改字号字重;交互类:漏抄次级交互(长按/滑动/回弹)、给纯展示元素加点击/loading、简化表单校验、改弹层触发/关闭方式、动效自作主张——全是 AI 多加了一层原型没有、或漏了原型本有的东西
- 完整流程见 `.opencode/skills/html2vue/SKILL.md`

### ❸ 前后端隔离开发铁律(不可商量)

**`php/` 和 `vue/` 严格隔离,一次任务只改一端,另一端绝对不能动。**

| 场景 | 允许改 | 禁止改 |
|---|---|---|
| 改 PHP 接口 / 数据表 / 字段 / 路由 / Service / Model | `php/**` | `vue/**` 绝对不动 |
| 改 Vue 页面结构 / 显示字段增减 / 样式 / 交互 | `vue/**` | `php/**` 绝对不动 |

**即使出现以下情况,也不得擅自跨越隔离:**
- 前端页面打开报错(因后端字段变动导致)→ **不能改 vue**,告知用户由前端单独处理
- 前端需要新字段 → **不能改 php**,告知用户走后端单独任务
- 同一个功能看似需要两端联动 → **拆成两个独立任务**,一次只做一端

> 联调只通过「接口契约」对接。同一任务/commit 里同时出现 `php/**` 和 `vue/**` 改动 = 违规。

### ❹ 术语 → 目录映射铁律(听到术语立刻锁定目录,不可跨)

| 用户说的术语 | 锁定目录 | 含义 |
|---|---|---|
| **「B 端接口」** | `php/app/controller/admin/` + `php/app/service/admin/` | 后台管理接口(B 端)|
| **「C 端接口」** | `php/app/controller/api/` + `php/app/service/api/` | 面向用户的接口(C 端后端部分)|
| **「C 端页面」** | `vue/` | 面向用户的前端页面 |

**听到术语后的执行动作:**
1. 只在锁定目录内改动,其他目录**全程不动**
2. 同一任务只能命中**一个**术语(跨术语 = 跨目录 = 违规,拆成多个任务)
3. 路由同步限定:B 端接口只走 `Route::group('admin', ...)`,C 端接口只走 `Route::group('api', ...)`

> 例:用户说"改 C 端接口" → 只动 `php/app/{controller,service}/api/`,`admin/` + `vue/` 都不能碰。

---

## 执行机制

AI 助手编辑文件前:
1. 判断文件相对路径属于 `php/**` 还是 `vue/**`——**本次任务锁定一端,另一端全程不动**
2. 按上方强制约束加载对应技能:
   - `php/**` → 加载 php-code-style
   - `vue/**` 写代码 → 加载 h5-code-style
   - `vue/**` 且任务是 HTML 转 Vue(触发词「html 转 vue」「html 生成 vue」「还原原型」等) → 加载 html2vue
3. 遵循 [docs/AGENTS_GLOBAL.md](docs/AGENTS_GLOBAL.md) 建立待办任务与影响面检查
4. 按技能规范编写代码
5. 改完按技能验证清单跑 `pnpm lint` / `pnpm build`(前端)或 §5.7 门禁 grep(后端)
6. 输出标准完成报告并提醒用户进行测试验证
7. 若发现需要对端配合改动 → **停止,告知用户**,由用户决定是否开新任务

---

## 子目录自治(可选)

- `php/AGENTS.md`(可选): PHP 项目特定配置(数据库连接 / 路由分组 / .env 实际值)
- `vue/AGENTS.md`(已由 init-project 从 templates/vue-agents.md 复制): 前端项目内部规范,可在项目内改写

子目录 AGENTS.md 优先级 > 根 AGENTS.md。

---

## 子技能升级

php-code-style 升级:
`cp ~/.config/opencode/skills/init-project/templates/php-code-style.SKILL.md .opencode/skills/php-code-style/SKILL.md`

h5-code-style 升级:
`cp ~/.config/opencode/skills/init-project/templates/h5-code-style.SKILL.md .opencode/skills/h5-code-style/SKILL.md`

html2vue 升级:
`cp ~/.config/opencode/skills/init-project/templates/html2vue.SKILL.md .opencode/skills/html2vue/SKILL.md`

vue 脚手架升级(整目录覆盖):
`rsync -a --exclude='.DS_Store' --exclude='node_modules' --exclude='pnpm-lock.yaml' --exclude='dist' ~/.config/opencode/skills/init-project/templates/h5-scaffold/ vue/`

vue AGENTS.md 升级:
`cp ~/.config/opencode/skills/init-project/templates/vue-agents.md vue/AGENTS.md`
