/**
 * 本地存储封装
 * - 封装 localStorage / sessionStorage 的读写，统一处理 JSON 序列化
 * - 避免业务代码直接操作原生 Storage API
 */

export type StorageType = 'local' | 'session'

/**
 * 读取本地存储项
 * @param key - 存储键名
 * @param type - 存储类型：local 或 session，默认 local
 * @returns 解析后的值；不存在或解析失败时返回 null
 */
export function getItem<T = unknown>(key: string, type: StorageType = 'local'): T | null {
  const storage = type === 'session' ? sessionStorage : localStorage
  const value = storage.getItem(key)
  if (value === null) return null
  try {
    return JSON.parse(value) as T
  } catch {
    return value as unknown as T
  }
}

/**
 * 写入本地存储项
 * @param key - 存储键名
 * @param value - 存储值，对象会自动 JSON 序列化
 * @param type - 存储类型：local 或 session，默认 local
 */
export function setItem(key: string, value: unknown, type: StorageType = 'local'): void {
  const storage = type === 'session' ? sessionStorage : localStorage
  storage.setItem(key, typeof value === 'string' ? value : JSON.stringify(value))
}

/**
 * 移除本地存储项
 * @param key - 存储键名
 * @param type - 存储类型：local 或 session，默认 local
 */
export function removeItem(key: string, type: StorageType = 'local'): void {
  const storage = type === 'session' ? sessionStorage : localStorage
  storage.removeItem(key)
}

/**
 * 清空本地存储
 * @param type - 存储类型：local 或 session，默认 local
 */
export function clear(type: StorageType = 'local'): void {
  const storage = type === 'session' ? sessionStorage : localStorage
  storage.clear()
}
