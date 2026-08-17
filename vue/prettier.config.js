/**
 * Prettier 格式化配置
 * - 统一团队代码风格（换行、引号、分号、空格等）
 * - ESLint 管代码质量，Prettier 管格式；eslint-config-prettier 关闭冲突规则
 */

export default {
  // 单行最大字符数，超过则换行
  printWidth: 100,
  // 缩进宽度（空格数）
  tabWidth: 2,
  // 使用空格而非 Tab 缩进
  useTabs: false,
  // 语句末尾不加分号
  semi: false,
  // 字符串使用单引号
  singleQuote: true,
  // 对象属性引号策略：仅在需要时加引号
  quoteProps: 'as-needed',
  // 多行数组/对象末尾保留逗号
  trailingComma: 'all',
  // 对象字面量括号内侧加空格
  bracketSpacing: true,
  // 箭头函数参数始终使用括号
  arrowParens: 'always',
  // 换行符使用 LF
  endOfLine: 'lf',
  // HTML 空白敏感度：按 CSS 显示规则处理
  htmlWhitespaceSensitivity: 'css',
  // Vue 文件中的 script 与 style 标签内容也进行缩进
  vueIndentScriptAndStyle: true,
}
