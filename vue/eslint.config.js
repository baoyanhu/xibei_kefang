/**
 * ESLint 9 flat config
 * - 集成 JS / TypeScript / Vue 推荐规则
 * - 关闭 Prettier 冲突规则，由 Prettier 负责格式
 * - 自定义团队规则：命名规范、console 限制、未使用变量等
 */

import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import eslintPluginVue from 'eslint-plugin-vue'
import eslintConfigPrettier from 'eslint-config-prettier'
import globals from 'globals'

export default [
  {
    // 忽略列表：构建产物、依赖、公共资源、自动生成类型声明
    ignores: [
      'dist/**',
      'node_modules/**',
      'public/**',
      'types/auto-import.d.ts',
      '.eslintrc-auto-import.json',
    ],
  },

  // JS 推荐规则
  js.configs.recommended,
  // TypeScript 推荐规则
  ...tseslint.configs.recommended,
  // Vue 单文件组件推荐规则
  ...eslintPluginVue.configs['flat/recommended'],
  // 关闭与 Prettier 冲突的规则
  eslintConfigPrettier,

  {
    languageOptions: {
      // 注册 browser 与 node 全局变量，避免 no-undef 误报
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
  },

  {
    // 针对 TS 与 Vue 文件的解析配置与规则
    files: ['**/*.{ts,tsx,vue}'],
    languageOptions: {
      parserOptions: {
        // Vue 文件内部使用 TypeScript 解析器
        parser: tseslint.parser,
        // 使用最新 ECMAScript 语法
        ecmaVersion: 'latest',
        // 使用 ES Modules
        sourceType: 'module',
      },
    },
    rules: {
      // 允许显式 any（规范 §5.2 折中方案：API params 有签名，业务层放开 any）
      '@typescript-eslint/no-explicit-any': 'off',
      // 未使用变量报错，但忽略以下划线开头的参数/变量/捕获错误
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],

      // Vue 属性顺序警告
      'vue/attributes-order': 'warn',
      // HTML 自闭合标签规则：SVG/Math 强制自闭合，普通标签自由
      'vue/html-self-closing': [
        'error',
        {
          html: { void: 'any', normal: 'any', component: 'any' },
          svg: 'always',
          math: 'always',
        },
      ],
      // 关闭多词组件名强制要求(Vant 单词组件名可豁免)
      'vue/multi-word-component-names': 'off',
      // 关闭 require-default-prop（TS script setup 用 withDefaults，此规则不兼容）
      'vue/require-default-prop': 'off',
      // 组件定义名称必须使用 PascalCase
      'vue/component-definition-name-casing': ['error', 'PascalCase'],
      // 模板中组件标签必须使用 PascalCase
      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
      // 允许使用 v-html（业务需要时自行 XSS 过滤）
      'vue/no-v-html': 'off',

      // 限制 console 使用，仅允许 warn / error
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      // 禁止 debugger 保留为警告
      'no-debugger': 'warn',
      // 优先使用 const
      'prefer-const': 'error',
      // 禁止 var
      'no-var': 'error',
    },
  },
]
