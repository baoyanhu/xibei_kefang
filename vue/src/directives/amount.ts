/**
 * v-amount 指令：金额输入框
 * - 仅允许数字和小数点；自动处理多小数点合并、首位 0 去重、首字符为点补 0
 * - 默认 2 位小数；可通过 binding.value 指定小数位数
 * - 用法：<van-field v-amount /> 或 <van-field v-amount="4" />
 *
 * 与字符过滤类指令差异：过滤逻辑依赖小数位参数（来自 binding），无法用工厂，独立实现
 */

import type { Directive, DirectiveBinding } from 'vue'
import { attachInputFilter, type FilterCleanup } from './shared'

// 默认小数位数
const DEFAULT_DECIMAL_LEN = 2

// 各宿主的清理函数（amount 独立维护，不与工厂共享 detachMap）
const detachMap = new WeakMap<HTMLElement, FilterCleanup>()

/**
 * 构造金额过滤函数
 * @param decimalLen - 小数点后允许的最大位数
 * @returns 过滤函数
 */
function createAmountFilter(decimalLen: number): (value: string) => string {
  return (value: string): string => {
    // 1. 去除非数字和非点字符
    let v = value.replace(/[^\d.]/g, '')

    // 2. 多个点只保留第一个：取第一个点为界，整数部分 + 小数部分（剔除多余点）
    const firstDotIdx = v.indexOf('.')
    if (firstDotIdx !== -1) {
      const intPart = v.substring(0, firstDotIdx)
      const rawDecimal = v.substring(firstDotIdx + 1).replace(/\./g, '')
      // 小数部分非空：拼接 + 截取指定位数；为空：保留尾部点便于用户继续输入
      v = rawDecimal ? `${intPart}.${rawDecimal.slice(0, decimalLen)}` : `${intPart}.`
    }

    // 3. 首位为 0 且第二位不是点：去掉首位 0（避免 0123 这种输入）
    if (v.length > 1 && v[0] === '0' && v[1] !== '.') {
      v = v.substring(1)
    }

    // 4. 以点开头：补 0（.12 → 0.12）
    if (v.startsWith('.')) {
      v = `0${v}`
    }

    return v
  }
}

const amountDirective: Directive<HTMLElement, number | undefined> = {
  mounted(el: HTMLElement, binding: DirectiveBinding<number | undefined>): void {
    const decimalLen = typeof binding.value === 'number' ? binding.value : DEFAULT_DECIMAL_LEN
    const filter = createAmountFilter(decimalLen)
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

export default amountDirective
