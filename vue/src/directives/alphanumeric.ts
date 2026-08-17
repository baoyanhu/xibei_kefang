/**
 * v-alphanumeric 指令：输入框仅允许数字 + 字母（不含下划线）
 * - 过滤掉非 [a-zA-Z0-9] 字符
 * - 用法：<van-field v-alphanumeric />（典型场景：短码、激活码）
 */

import { createInputFilterDirective } from './shared'

// 仅保留数字和字母（不含下划线）
const filterAlphanumeric = (value: string): string => value.replace(/[^a-zA-Z0-9]/g, '')

const alphanumericDirective = createInputFilterDirective(filterAlphanumeric)

export default alphanumericDirective
