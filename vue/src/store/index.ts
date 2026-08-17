/**
 * Pinia 入口
 * - 创建 Pinia 实例
 * - 注册 persistedstate 插件，使 store 中的指定字段自动持久化到 localStorage
 */

import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

// Pinia 实例，集成 persistedstate 持久化插件
const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)

export default pinia
