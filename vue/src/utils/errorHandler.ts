/**
 * 全局错误捕获
 * - Vue 组件内错误（app.config.errorHandler）
 * - 未捕获的同步错误（window error）
 * - 未处理的 Promise rejection（unhandledrejection）
 * - DEV：打 console.error（vconsole 可见）
 * - PROD：预留 reportError() 上报钩子，接入监控平台（Sentry / 自建）时填实现
 */
import type { App } from 'vue'

type ErrorType = 'vue' | 'window' | 'promise'

// 上报钩子：接入监控平台后在此实现（当前空实现，避免基线依赖外部服务）
function reportError(type: ErrorType, err: unknown) {
  // TODO: 接入 Sentry / 自建监控后，在此上报 { type, err }
  void type
  void err
}

// 错误对象格式化（保证栈信息可见）
function format(err: unknown): string {
  if (err instanceof Error) return `${err.name}: ${err.message}\n${err.stack ?? ''}`
  if (typeof err === 'object' && err !== null) {
    try {
      return JSON.stringify(err)
    } catch {
      return String(err)
    }
  }
  return String(err)
}

/**
 * 挂载全局错误捕获
 * @param app Vue 应用实例
 */
export function setupErrorHandler(app: App) {
  // 1. Vue 组件内错误（render / lifecycle / watcher / 事件回调）
  app.config.errorHandler = (err, _instance, info) => {
    console.error('[Vue Error]', info, '\n', format(err))
    reportError('vue', err)
  }

  // 2. 未捕获的同步错误
  window.addEventListener('error', (event) => {
    console.error(
      '[Window Error]',
      `${event.message} @ ${event.filename}:${event.lineno}:${event.colno}`,
    )
    reportError('window', event.error)
  })

  // 3. 未处理的 Promise rejection
  window.addEventListener('unhandledrejection', (event) => {
    console.error('[Unhandled Rejection]', format(event.reason))
    reportError('promise', event.reason)
  })
}
