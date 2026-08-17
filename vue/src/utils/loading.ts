/**
 * 全局骨架屏计数器（框架级）
 * - request.ts 拦截器在请求发出/落地时调用 incLoading/decLoading
 * - AppSkeleton.vue 读取 loadingState.visible 决定显隐，业务页面零代码
 * - 请求发出立即显示（首屏白屏比骨架闪一下更糟）；最短展示 400ms 防快请求频闪
 * - 分页/静默刷新/弹层取数在调用处传 { silent: true } 跳过计数（见 request.ts）
 */

import { reactive } from 'vue'

// 骨架屏状态：pending=进行中请求数，visible=是否显示骨架屏
export const loadingState = reactive({
  pending: 0,
  visible: false,
})

// 最短展示时长：请求过快返回时骨架屏也至少展示该时长，防频闪
const MIN_DURATION = 400

// 骨架屏出现的时间戳（内部变量，不导出）
let _shownAt = 0
// 最短时长兜底隐藏定时器（内部变量，不导出）
let _hideTimer: ReturnType<typeof setTimeout> | null = null

/** 请求发出时 +1（request.ts 请求拦截器调用，silent 请求不调） */
export function incLoading(): void {
  loadingState.pending++
  if (!loadingState.visible) {
    loadingState.visible = true
    _shownAt = Date.now()
  }
}

/** 请求落地时 -1（request.ts 响应拦截器成功/失败两个分支都必须调用） */
export function decLoading(): void {
  loadingState.pending = Math.max(0, loadingState.pending - 1)
  if (loadingState.pending > 0) return
  // 归零但展示不足最短时长：延迟隐藏；等待期间新请求进来由回调里的 pending>0 挡住
  const remain = MIN_DURATION - (Date.now() - _shownAt)
  if (remain <= 0) {
    loadingState.visible = false
    return
  }
  if (_hideTimer) clearTimeout(_hideTimer)
  _hideTimer = setTimeout(() => {
    _hideTimer = null
    if (loadingState.pending === 0) loadingState.visible = false
  }, remain)
}
