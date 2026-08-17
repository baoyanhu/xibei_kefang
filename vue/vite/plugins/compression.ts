/**
 * 按 VITE_BUILD_COMPRESS 生成 gzip / brotli 产物
 * - 仅在生产构建时启用
 * - 根据环境变量决定生成 gzip、brotli 或两者同时生成
 * - 过滤掉已压缩或无需压缩的资源类型
 */

import { compression } from 'vite-plugin-compression2'
import type { PluginOption } from 'vite'

/**
 * 创建压缩插件
 * @param env - 当前 mode 加载的环境变量集合
 * @param isBuild - 是否为生产构建
 * @returns 压缩插件或空数组（非构建模式 / 关闭压缩时）
 */
export function createCompressionPlugin(
  env: Record<string, string>,
  isBuild: boolean,
): PluginOption {
  // 开发模式下不生成压缩产物
  if (!isBuild) return []

  // 读取环境变量决定压缩算法，默认 gzip
  const mode = env.VITE_BUILD_COMPRESS || 'gzip'
  // 关闭压缩时直接返回空数组
  if (mode === 'none') return []

  // 压缩插件通用配置
  const base = {
    // 仅对超过 10KB 的资源进行压缩
    threshold: 10240,
    // 排除 sourcemap 与图片资源（本身已压缩或无需 gzip）
    exclude: [/\.(map)$/, /\.(png|jpe?g|webp|gif|svg)$/],
  }

  // 同时生成 gzip 与 brotli 两种压缩产物
  if (mode === 'both') {
    return [
      compression({ ...base, algorithm: 'gzip' }),
      compression({ ...base, algorithm: 'brotliCompress' }),
    ]
  }

  // 单种压缩产物：brotli 或 gzip
  return compression({ ...base, algorithm: mode === 'brotli' ? 'brotliCompress' : 'gzip' })
}
