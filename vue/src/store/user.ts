/**
 * 用户级 Store
 * - token 写死在此（占位），项目接手后替换为自己的获取逻辑（登录接口 / URL / SSO 等）
 * - token 持久化直接使用 utils/token.ts，本 store 不做二次封装
 */

import { defineStore } from 'pinia'
import { setToken } from '@/utils/token'

// 写死的占位 token：置空则 request.ts 拦截所有请求并跳 403
const PLACEHOLDER_TOKEN = 'fe-spec-h5-placeholder-token'

// 用户级 store：启动时把占位 token 写入 localStorage，供 request.ts 注入请求头
export const useUserStore = defineStore('user', () => {
  if (PLACEHOLDER_TOKEN) {
    setToken(PLACEHOLDER_TOKEN)
  }
})
