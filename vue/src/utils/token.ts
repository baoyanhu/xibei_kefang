/**
 * Token 操作封装
 * - 封装 token 的读取、设置、清除
 * - 统一 token 在 localStorage 中的 key，避免散落在各业务文件中
 */

import { getItem, removeItem, setItem } from './storage'

// token 在 localStorage 中的键名
const TOKEN_KEY = 'token'

/**
 * 读取 token
 * @returns token 字符串；不存在时返回 null
 */
export function getToken(): string | null {
  const token = getItem<string>(TOKEN_KEY)
  return typeof token === 'string' ? token : null
}

/**
 * 设置 token
 * @param value - 登录后返回的 token 字符串
 */
export function setToken(value: string): void {
  setItem(TOKEN_KEY, value)
}

/**
 * 清除 token
 */
export function removeToken(): void {
  removeItem(TOKEN_KEY)
}
