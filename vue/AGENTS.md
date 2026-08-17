# H5 端项目宪法

> Vue3 + Vite + TypeScript + Vant4(按需引入)+ Pinia + pnpm
>
> 本文件只回答三件事:**这是什么项目 / 框架有什么能力 / 哪些能改哪些不能改**。
>
> 代码怎么写 → 技能 `h5-code-style`(`.opencode/skills/h5-code-style/SKILL.md`);HTML 原型怎么转 Vue → 技能 `html2vue`(`.opencode/skills/html2vue/SKILL.md`);本文件不重复。

---

## 一、项目定位

H5 端标准框架基线仓库(fe-spec/h5/),基于 Vue3 + Vite + TypeScript + Vant4 + Pinia + pnpm。

- **三端独立**:H5 与 PC(`pc/`)、小程序(`mp/`)**独立开发**,不存在映射或同步关系,详见根 `AGENTS.md`
- **定位**:框架能力、目录结构、依赖版本、工程化门禁等基础设施在此沉淀;真实项目复制本端内容后**只写业务代码**
- **只读基线**:复制到真实项目后的框架内容视为只读,要改标准 → 回本仓库改 → 再重新复制覆盖

---

## 二、技术栈与依赖基线

完整清单见 `package.json`。核心版本:

| 类别       | 包                                                                                              | 版本                                                    |
| ---------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| 框架       | vue / vue-router / pinia                                                                        | ^3.5.41 / ^4.6.4 / ^3.0.4                               |
| 状态持久化 | pinia-plugin-persistedstate                                                                     | ^4.7.1                                                  |
| HTTP       | axios                                                                                           | ^1.19.0                                                 |
| 图表       | echarts / vue-echarts                                                                           | ^6.1.0 / ^8.1.0                                         |
| UI         | vant(按需引入)                                                                                  | ^4.10.0                                                 |
| 调试       | vconsole(DEV 自动挂)                                                                            | ^3.15.1(devDependencies)                                |
| 构建       | vite / @vitejs/plugin-vue                                                                       | ^6.4.3 / ^6.0.8                                         |
| 类型       | typescript / vue-tsc / @types/node                                                              | ^5.9.3 / ^2.2.12 / ^22.20.1                             |
| 样式       | sass                                                                                            | ^1.102.0                                                |
| 适配       | postcss-px-to-viewport-8-plugin                                                                 | ^1.2.5                                                  |
| Mock(DEV)  | mockjs / vite-plugin-mock / @types/mockjs                                                       | ^1.1.0(dep)/ ^3.0.2(dev)/ ^1.0.10(dev)                  |
| Lint       | eslint / @eslint/js / typescript-eslint / eslint-plugin-vue / eslint-config-prettier / prettier | ^9.39.5 / ^9.39.5 / ^8.66.0 / ^9.33.0 / ^9.1.2 / ^3.5.3 |
| 全局变量   | globals                                                                                         | ^16.5.0                                                 |
| Git 钩子   | husky / lint-staged                                                                             | ^9.1.7 / ^15.5.2                                        |
| Vite 插件  | unplugin-vue-components / vite-plugin-compression2                                              | ^0.28.0 / ^1.4.0                                       |

**包管理器**:`pnpm@9.15.9`(已锁定 `packageManager` 字段)

---

## 三、目录结构

```
vue/
├── AGENTS.md               ← 你正在读(端宪法)
├── package.json            依赖基线 + scripts + lint-staged + packageManager
├── vite.config.ts          构建配置(loadEnv + base + manualChunks)
├── tsconfig.json           TS 配置入口(references → app/node)
├── tsconfig.app.json       TS 应用配置(含三严格规则)
├── tsconfig.node.json      TS 构建脚本配置(vite.config + vite/plugins)
├── index.html              入口模板(viewport 三连 + favicon + #app)
├── Jenkinsfile             CI/CD 模板(声明式 pipeline)
├── eslint.config.js        ESLint 9 flat config
├── prettier.config.js      Prettier 12 项
├── postcss.config.cjs      vw 适配(px→vw 编译转换,设计稿 750)
├── public/                 静态资源(favicon.svg,构建时原样拷贝到 dist)
├── .env / .env.development/.test/.production
├── .husky/pre-commit       husky 钩子(lint-staged)
├── .vscode/extensions.json 团队推荐插件
│
├── types/
│   └── components.d.ts     unplugin-vue-components 自动生成(勿手改)
│
├── vite/
│   └── plugins/            Vite 插件工厂(函数化拆分)
│       ├── index.ts        createVitePlugins(env, isBuild) 装配入口
│       ├── components.ts   Vant 组件/图标自动注册(VantResolver)
│       ├── compression.ts  gzip/brotli 压缩(按 VITE_BUILD_COMPRESS 开关)
│       └── mock.ts         DEV mock 服务器(vite-plugin-mock,仅 DEV 启用)
│
└── src/                    业务源码(复制到真实项目时整个 src/ 直接拷贝)
    ├── main.ts             入口(createApp + router + pinia + 占位 token + vconsole)
    ├── App.vue             根组件(router-view + fade 过渡)
    ├── vite-env.d.ts       vite/client + *.vue 类型声明
    │
    ├── assets/
    │   ├── styles/
    │   │   ├── base.scss   全局基线(reset + body + 安全区 + z-index 变量 + ui- 工具类)
    │   │   ├── tokens.scss 业务 token(基线空占位;html2vue 生成时缺样式就补进、直接用)
    │   │   └── theme.scss  Vant4 主题覆盖(基线中性骨架;html2vue 生成时缺样式就补进、直接用)
    │   └── images/         png 图片 + 图标(html2vue 迁移落点;图标统一 png,不用 svg)
    │
    ├── api/                接口请求 + DEV mock(按项目隔离,见 §5.1)
    │
    ├── router/
    │   ├── index.ts        路由聚合(import.meta.glob 自动加载 ./modules/*.ts)+ beforeEach 守卫
    │   └── modules/         项目级路由文件(一个项目一个 <projectName>.ts,落此自动接入)
    │
    ├── plugins/            全局插件
    │   └── echarts.ts      ECharts 按需注册(柱/线/饼 + 常用组件)+ 全局 VChart
    │
    ├── store/
    │   ├── index.ts        createPinia + persistedstate 插件
    │   └── user.ts         用户 store(占位 token 写死在此,项目接手改这里)
    │
    ├── utils/
    │   ├── request.ts      axios 封装(ApiResponse<T> 泛型 + 200/403/500 响应契约)
    │   ├── token.ts        token 读写唯一入口(localStorage 单源)
    │   ├── storage.ts      localStorage/sessionStorage 封装(JSON 序列化)
    │   └── vw.ts           vw()/realPx() 适配 helper(inline/JS/组件 prop 用)
    │
    ├── components/         框架级共用组件(跨项目复用;下沉需按h5-code-style §4.7 确认)
    │
    ├── views/              页面(按项目隔离,见 §5.1)
    │   ├── <projectName>/  一个项目一个子目录(小驼峰)
    │   │   ├── *.vue       页面 SFC(小驼峰)
    │   │   └── components/ 项目专属组件(大驼峰)
    │   ├── home.vue        根路径 / 占位页(空骨架,项目接手填充)
    │   └── error/          框架级公共错误页
    │       ├── 403.vue     无权限页(token 为空或 code 403 落点,纯静态)
    │       └── 404.vue     兜底页
    │
    ├── directives/         全局自定义指令(输入过滤类,5 个)
    │   ├── index.ts        setupDirectives(app) 注册入口
    │   ├── shared.ts       通用工具
    │   ├── number.ts       v-number 仅数字
    │   ├── ncul.ts         v-ncul 数字+字母+下划线
    │   ├── alphanumeric.ts v-alphanumeric 数字+字母
    │   ├── cn-alphanumeric.ts  v-cn-alphanumeric 中文+英文+数字
    │   └── amount.ts       v-amount 金额(默认 2 位小数,可传参指定位数)
```

---

## 四、框架能力清单(已就绪,业务无需自建)

> 本清单列**有什么能力**(事实);**怎么用**见 `h5-code-style` 技能对应章节。
>
> ⚠️ 本清单及 `utils/` / `store/index.ts` / `directives/` / `plugins/` / `assets/styles/base.scss` / `vite/` / `main.ts` / `App.vue` / 配置文件等框架级文件**一律只读**,详见 §5.2。(`tokens.scss` / `theme.scss` 是 html2vue 抽取填充目标,不在只读之列)

| 能力           | 入口                                     | 说明                                                                                | 用法见            |
| -------------- | ---------------------------------------- | ----------------------------------------------------------------------------------- | ----------------- |
| 状态管理       | `store/index.ts`                         | pinia + persistedstate(token 不经此插件)                                            | h5-code-style §7.4-7.5 |
| token 管理     | `store/user.ts` + `utils/token.ts`       | 占位 token 写死在 user.ts;持久化用 utils/token.ts                                   | h5-code-style §7.6     |
| 路由懒加载     | `router/index.ts`                        | 全量 `() => import()`                                                               | h5-code-style §11.3    |
| 路由守卫       | `router/index.ts` beforeEach             | 设置 document.title(来自 meta.title)                                                | h5-code-style §11.2    |
| 请求封装       | `utils/request.ts`                       | `request()` 封装 + `ApiResponse` 响应契约（拦截器剥壳返 data / 403 跳页 / 500 Toast） | h5-code-style §7.1-7.2 |
| 图表           | `plugins/echarts.ts`                     | echarts 按需注册,全局 `<VChart :option>`                                            | h5-code-style §10.2    |
| 鉴权失败处理   | `utils/request.ts` 拦截器                | token 为空跳 403;200 返 data / 403 跳无权限页 / 500 Toast                           | h5-code-style §7.6     |
| vw 自适应      | `postcss.config.cjs` + `utils/vw.ts`     | 设计稿 750px,class 写 px 编译转 vw;inline/JS/prop 用 `vw()`                         | h5-code-style §6.1     |
| DEV 调试       | `main.ts` vconsole                       | 仅 `import.meta.env.DEV` 开启                                                       | —                 |
| 多环境         | `.env.*`                                 | `vite build --mode test` 切环境                                                     | h5-code-style §7.7     |
| gzip/brotli    | `vite/plugins/compression.ts`            | 按 `VITE_BUILD_COMPRESS` 开关                                                       | —                 |
| 提交前门禁     | `.husky/pre-commit` + `lint-staged`      | eslint --max-warnings=0 + prettier --write                                          | h5-code-style §12.1    |
| TS 严格        | `tsconfig.app.json`                      | noUnusedLocals / noUnusedParameters / noFallthroughCasesInSwitch                    | —                 |
| sourcemap      | `vite.config.ts`                         | 默认关闭;需堆栈还原时手动改 `'hidden'`                                              | —                 |
| 输入过滤指令   | `directives/index.ts`                    | v-number / v-ncul / v-alphanumeric / v-cn-alphanumeric / v-amount(仅 `<van-field>`) | h5-code-style §10.1    |
| Mock(DEV)      | `vite/plugins/mock.ts` + `api/*.mock.ts` | vite-plugin-mock 仅 DEV 启用;mock 自包含、按项目隔离                                | h5-code-style §八      |
| 框架级共用组件 | `src/components/`                        | 跨项目复用组件的落点；判定见h5-code-style §4.7；运行时直接查 `src/components/` 有哪些可用 | h5-code-style §4.7     |
| 全局骨架屏     | `components/AppSkeleton.vue` + `utils/loading.ts` | **有接口请求必走**：拦截器计数自动全屏骨架（立即显示 + 400ms 最短展示防频闪；`config.silent` 跳过），业务禁手写 loading | h5-code-style §7.3     |
| 空态/错误态组件 | `components/AppEmpty.vue`                | **数据空/请求失败必用**：page/block 双形态 + error 重试按钮，样式自持，业务禁手写 van-empty | h5-code-style §7.3     |

---

## 五、项目隔离边界(多项目共存)

### 5.1 三目录隔离(项目级,可改)

一个项目 = 三个目录,命名小驼峰(规范 §2.1):

| 目录                                                 | 内容                                                      | 示例                                |
| ---------------------------------------------------- | --------------------------------------------------------- | ----------------------------------- |
| `src/views/<projectName>/`                           | 页面 SFC(小驼峰 .vue)+ 项目专属组件(`components/` 大驼峰) | `views/myProject/home.vue`    |
| `src/api/<projectName>.ts` + `<projectName>.mock.ts` | 接口 + DEV mock(并排)                                     | `api/myProject.ts`            |
| `src/router/modules/<projectName>.ts`                | 项目路由(落 modules/ 自动聚合)                            | `router/modules/myProject.ts` |

**禁止**:

- 在 `src/views/` 根目录散放页面(必须 `views/<projectName>/`)
- 在 `src/components/` 放项目专属组件(`src/components/` 是框架级,项目组件落 `views/<project>/components/`)
- 开 `src/types/` 目录(类型不定义;参数统一顶部一份 `postData` 写在 `api/<project>.ts`)
- 在 `views/<project>/` 根建项目级 `helper.ts` / `styles.scss` 等非 `.vue` 文件(函数就地写进 `.vue`、样式进组件 scoped 或 theme.scss;反过度工程化见h5-code-style §4.1,样式分发见 §6.3)

> `tokens.scss` / `theme.scss` 是 html2vue 从原型抽取的填充目标(见h5-code-style §6.3),不算"项目级样式文件",不受此禁项约束。

### 5.2 框架级只读清单

以下文件**一律只读**,业务侧不动:

| 类别      | 文件                                                                                                   |
| --------- | ------------------------------------------------------------------------------------------------------ |
| 入口/根   | `main.ts` / `App.vue` / `vite-env.d.ts`                                                                |
| 工具      | `utils/*.ts`(request/token/storage/vw)                                                                 |
| 状态      | `store/index.ts`                                                                                       |
| 路由聚合  | `router/index.ts`(项目路由落 modules/ 不动 index)                                                      |
| 插件      | `plugins/*.ts`                                                                                         |
| 指令      | `directives/*.ts`                                                                                      |
| 样式      | `assets/styles/base.scss`(框架只读) — `tokens.scss`+`theme.scss` 是 html2vue 抽取填充目标,不在只读列   |
| 构建      | `vite.config.ts` / `tsconfig*.json` / `postcss.config.cjs` / `eslint.config.js` / `prettier.config.js` |
| Vite 插件 | `vite/plugins/*.ts`                                                                                    |

**一个例外**(项目接手可改):

- `store/user.ts` — 占位 token 写死在此,项目接手改这里

> `tokens.scss` + `theme.scss` 基线只是空占位/中性骨架,不在框架只读之列。**html2vue 生成 Vue 时,一旦 1:1 还原缺 token/全局样式,就把原型对应值补进这两个文件再直接用**(`:root` 语义变量 → tokens.scss、全局 `<style>` → theme.scss),不凭先验编、不塞进组件 scoped 凑合,见h5-code-style §6.3。

### 5.3 越界确认（下沉框架级）

项目硬栅栏见h5-code-style §4.7：三目录之外皆越界。遇到应跨项目复用的组件/样式/能力（原型里 ≥2 处复用），需要落到 `src/components/` / `assets/styles/` / `utils/` 等**项目目录之外**的位置时：

1. **向用户交互确认**"建议补建/下沉到框架级"，说明理由
2. **用户同意** → 在对应框架级目录创建/修改文件（就是"下沉"）
3. **用户拒绝 / 暂不答复** → 项目级实现，不动框架级

**不许静默退回项目级而不把框架级方案摆给用户**。框架组件是否存在以 `src/components/` 文件系统为准，不维护台账。

---

## 六、配套文档与技能索引

| 文档/技能                                                   | 回答的问题                           | 读者        |
| ----------------------------------------------------------- | ------------------------------------ | ----------- |
| 本文件                                                      | 这是什么项目 / 框架有什么 / 哪些能改 | AI + 维护者 |
| `.opencode/skills/h5-code-style/SKILL.md`                  | 这一行/这个文件代码该怎么写          | AI + 开发者 |
| 技能 `html2vue`(`.opencode/skills/html2vue/SKILL.md`)       | HTML 原型怎么转 Vue 项目             | AI          |

---

_版本:v6.5 / 2026-08-17 / 能力清单收紧骨架屏/空态两条为「有接口必走 AppSkeleton、数据空必用 AppEmpty、业务禁手写」（口径对齐 h5-code-style §7.3）_

_历史:v6.4 / 2026-08-17 / 能力清单新增全局骨架屏（AppSkeleton + utils/loading.ts）与空态组件（AppEmpty）_

_历史:v6.3 / 2026-08-14 / tokens/theme 定为「缺样式就补进文件、直接用」：基线空占位/中性骨架，html2vue 生成时把原型缺失的 token/全局样式补进再引用（回退 v6.2「产品维护、不抽取」导致的颜色失真）_
