/**
 * Vite 插件装配入口
 * - Vue 编译 + 组件自动注册 + 压缩 + DEV mock
 */

import type { PluginOption } from 'vite'
import vue from '@vitejs/plugin-vue'
import Components from './components'
import { createCompressionPlugin } from './compression'
import { createMockPlugin } from './mock'

export function createVitePlugins(env: Record<string, string>, isBuild: boolean): PluginOption[] {
  const plugins: PluginOption[] = [
    vue(),
    Components(),
    createCompressionPlugin(env, isBuild),
  ]
  if (!isBuild) plugins.push(createMockPlugin())
  return plugins
}
