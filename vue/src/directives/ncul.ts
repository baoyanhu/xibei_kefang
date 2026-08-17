/**
 * v-ncul 指令：输入框仅允许数字 + 字母 + 下划线
 * - 等价正则 \w（[a-zA-Z0-9_]）
 * - 用法：<van-field v-ncul />（典型场景：用户名、编码）
 */

import { createInputFilterDirective } from './shared'

// 仅保留数字、字母、下划线
const filterNcul = (value: string): string => value.replace(/[^\w]/g, '')

const nculDirective = createInputFilterDirective(filterNcul)

export default nculDirective
