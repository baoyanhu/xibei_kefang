/**
 * axios 请求封装
 * - 统一注入 token，token 为空直接跳 403 无权限页
 * - 响应契约：code 200 返回 data；403 跳无权限页；500 用 Toast 抛异常；其它码仅 reject
 * - 提供 request<T> 泛型方法，使业务代码获得端到端类型推断
 * - 请求计数驱动全局骨架屏（AppSkeleton.vue）；config.silent=true 跳过计数（分页/静默刷新用）
 */

import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'
import { showToast } from 'vant'
import router from '@/router'
import { getToken } from './token'
import { incLoading, decLoading } from './loading'

// 自定义请求配置：silent=true 时本次请求不触发全局骨架屏（分页加载/静默刷新用）
declare module 'axios' {
  interface AxiosRequestConfig {
    silent?: boolean
  }
}

// 后端统一响应体
export interface ApiResponse<T = unknown> {
  // 业务状态码
  code: number
  // 业务提示信息
  message: string
  // 业务数据
  data: T
}

// 业务成功码
const SUCCESS_CODE = 200

// 跳 403 无权限页（403 页保持静态，避免与拦截器死循环）
function pushForbidden(): void {
  if (router.currentRoute.value.name !== 'Forbidden') {
    router.push({ name: 'Forbidden' })
  }
}

// axios 实例
const service: AxiosInstance = axios.create({
  // 从环境变量读取 API 基础地址，兜底 /api；业务 url 一律不再带 /api 前缀
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  // 请求超时时间：10 秒
  timeout: 10000,
})

// 请求拦截器：注入 token；token 为空直接跳 403 并中断请求（未发出不计数）
service.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken()
    if (!token) {
      pushForbidden()
      return Promise.reject(new Error('无有效 token'))
    }
    config.headers.Authorization = `Bearer ${token}`
    // 计数 +1 驱动全局骨架屏（silent 请求跳过）
    if (!config.silent) incLoading()
    return config
  },
  (error) => Promise.reject(error),
)

// 响应拦截器：按契约处理（200 返回 data / 403 跳页 / 500 Toast）；成功/失败两分支都要计数 -1
service.interceptors.response.use(
  (response: AxiosResponse<ApiResponse>) => {
    // 计数 -1（与请求拦截器对称；silent 请求未计数也不减）
    if (!response.config.silent) decLoading()
    const res = response.data

    if (res.code === SUCCESS_CODE) {
      return res.data as unknown as AxiosResponse
    }
    // 无权限：跳 403 页
    if (res.code === 403) {
      pushForbidden()
      return Promise.reject(new Error(res.message || '无权限'))
    }
    // 服务器异常：Toast 抛出
    if (res.code === 500) {
      showToast(res.message || '服务器异常')
      return Promise.reject(new Error(res.message || '服务器异常'))
    }
    return Promise.reject(new Error(res.message || 'Error'))
  },
  (error) => {
    // 计数 -1（失败分支；error.config 缺失说明请求未发出、未计过数，不能减）
    if (error.config && !error.config.silent) decLoading()
    console.error(`[网络错误] ${error.message}`)
    return Promise.reject(error)
  },
)

/**
 * 通用请求，返回业务 data 字段
 * @param config - axios 请求配置
 * @returns 业务 data 泛型结果
 */
export function request<T = unknown>(config: AxiosRequestConfig): Promise<T> {
  return service.request(config) as unknown as Promise<T>
}

export default service
