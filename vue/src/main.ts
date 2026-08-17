/**
 * 应用入口
 * - 创建 Vue 应用实例
 * - 注册路由、Pinia 状态管理
 * - 挂载全局错误捕获、输入过滤指令
 * - 加载全局样式与开发调试工具
 */

import { createApp } from 'vue'

import App from './App.vue'
import router from './router'
import pinia from './store'
import { useUserStore } from '@/store/user'
import { setupDirectives } from '@/directives'
import { setupErrorHandler } from '@/utils/errorHandler'

import '@/assets/styles/base.scss'
import '@/assets/styles/tokens.scss'
import '@/assets/styles/theme.scss'
import 'vant/lib/index.css'

// DEV 挂载 vconsole
if (import.meta.env.DEV) {
  import('vconsole')
    .then(({ default: VConsole }) => {
      new VConsole()
    })
    .catch((err) => {
      console.warn('[vconsole] 加载失败:', err)
    })
}

const app = createApp(App)

app.use(pinia)
useUserStore() // 写入占位 token 到 localStorage
app.use(router)
setupDirectives(app)
setupErrorHandler(app) // 全局错误捕获（Vue / Window / Promise）

app.mount('#app')
