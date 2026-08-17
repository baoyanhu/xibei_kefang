/**
 * v-cn-alphanumeric 指令：输入框仅允许中文 + 英文 + 数字
 * - 过滤掉表情、符号、空格等
 * - 用法：<van-field v-cn-alphanumeric />（典型场景：昵称、备注）
 */

import { createInputFilterDirective } from './shared'

// 仅保留中文、英文、数字
const filterCnAlphanumeric = (value: string): string =>
  value.replace(/[^\u4e00-\u9fa5a-zA-Z0-9]/g, '')

const cnAlphanumericDirective = createInputFilterDirective(filterCnAlphanumeric)

export default cnAlphanumericDirective
