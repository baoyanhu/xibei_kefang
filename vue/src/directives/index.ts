/**
 * 自定义指令注册入口
 * - 输入过滤类：v-number / v-ncul / v-alphanumeric / v-cn-alphanumeric / v-amount
 * - 在 main.ts 中通过 setupDirectives(app) 注册
 */

import type { App } from 'vue'
import numberDirective from './number'
import nculDirective from './ncul'
import alphanumericDirective from './alphanumeric'
import cnAlphanumericDirective from './cn-alphanumeric'
import amountDirective from './amount'

/**
 * 注册全局自定义指令
 * @param app - Vue 应用实例
 */
export function setupDirectives(app: App): void {
  app.directive('number', numberDirective)
  app.directive('ncul', nculDirective)
  app.directive('alphanumeric', alphanumericDirective)
  app.directive('cnAlphanumeric', cnAlphanumericDirective)
  app.directive('amount', amountDirective)
}
