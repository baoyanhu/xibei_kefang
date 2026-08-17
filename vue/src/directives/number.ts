/**
 * v-number 指令：输入框仅允许数字
 * - 过滤掉所有非数字字符（0-9）
 * - 用法：<van-field v-number />
 */

import { createInputFilterDirective } from './shared'

// 仅保留数字：0-9
const filterNumber = (value: string): string => value.replace(/[^\d]/g, '')

const numberDirective = createInputFilterDirective(filterNumber)

export default numberDirective
