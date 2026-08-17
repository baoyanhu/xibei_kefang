/**
 * 输入过滤指令通用工具
 * - 提供 attachInputFilter：在宿主元素上挂载 composition + input 监听，返回清理函数
 * - 提供 createInputFilterDirective 工厂：4 个字符过滤类指令共用（number/ncul/alphanumeric/cnAlphanumeric）
 * - 通用职责：IME 组合期间不过滤；过滤后派发原生 input 事件同步 v-model；卸载时清理监听
 */

import type { Directive, DirectiveBinding } from 'vue'

// UI 库 input 组件内部真实 input 的 class(Vant: .van-field__control / 其它库按实际适配)
const INPUT_CONTROL_SELECTOR = '.van-field__control'

/**
 * 在指令宿主元素下查找 UI 库 input 内部真实 input 元素
 * @param el - 指令绑定的宿主元素
 * @returns 真实 input 元素;未找到返回 null
 */
function queryInputControl(el: HTMLElement): HTMLInputElement | null {
  return el.querySelector<HTMLInputElement>(INPUT_CONTROL_SELECTOR)
}

/**
 * 对 input 元素应用过滤：值变化时改写并派发原生 input 事件同步 v-model
 * @param input - 真实 input 元素
 * @param filter - 字符过滤函数
 */
function applyFilter(input: HTMLInputElement, filter: (value: string) => string): void {
  const original = input.value
  const filtered = filter(original)
  if (filtered === original) return
  input.value = filtered
  // 用 Event 而非 InputEvent：避开 van-field handleInput 的 IME 守卫
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

// 卸载时调用的清理函数类型
export type FilterCleanup = () => void

/**
 * 在宿主元素上挂载输入过滤监听
 * @param el - 指令宿主元素
 * @param filter - 字符过滤函数
 * @returns 清理函数；宿主非 van-field 时返回 null
 */
export function attachInputFilter(
  el: HTMLElement,
  filter: (value: string) => string,
): FilterCleanup | null {
  const input = queryInputControl(el)
  if (!input) return null

  // IME 组合期间不过滤，避免中文输入法中途字符被吃
  let composing = false
  const onCompositionStart = (): void => {
    composing = true
  }
  const onCompositionEnd = (): void => {
    composing = false
    applyFilter(input, filter)
  }
  const onInput = (): void => {
    if (composing) return
    applyFilter(input, filter)
  }

  input.addEventListener('compositionstart', onCompositionStart)
  input.addEventListener('compositionend', onCompositionEnd)
  input.addEventListener('input', onInput)

  return () => {
    input.removeEventListener('compositionstart', onCompositionStart)
    input.removeEventListener('compositionend', onCompositionEnd)
    input.removeEventListener('input', onInput)
  }
}

// 存每个指令宿主的清理函数，卸载时取出调用
const detachMap = new WeakMap<HTMLElement, FilterCleanup>()

/**
 * 输入过滤指令工厂：4 个字符过滤类指令共用
 * @param filter - 字符过滤函数
 * @returns Vue 指令对象（mounted + unmounted）
 */
export function createInputFilterDirective(
  filter: (value: string) => string,
): Directive<HTMLElement> {
  return {
    mounted(el: HTMLElement, _binding: DirectiveBinding): void {
      const detach = attachInputFilter(el, filter)
      if (detach) detachMap.set(el, detach)
    },
    unmounted(el: HTMLElement): void {
      const detach = detachMap.get(el)
      if (detach) {
        detach()
        detachMap.delete(el)
      }
    },
  }
}
