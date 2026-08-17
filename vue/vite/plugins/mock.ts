/**
 * DEV mock 服务器插件
 * - 基于 vite-plugin-mock，DEV 时拦截 fetch 请求返回 mock 数据
 * - mock 文件约定：src/api 下（含子目录）所有 .mock.ts 文件，跟 api 业务文件并排
 * - 仅 DEV 启用（vite-plugin-mock 3.x 移除了生产 mock，配合 index.ts 的 !isBuild 双保险）
 * - 切换场景约定：URL 加 ?scenario=xxx 由 mock 文件内部按 query 返回不同数据
 */

import type { PluginOption } from 'vite'
import { viteMockServe } from 'vite-plugin-mock'

/**
 * 创建 mock 插件（仅 DEV 启用）
 * 调用方需保证仅在 DEV 调用（在 createVitePlugins 内部按 isBuild 判断）
 */
export function createMockPlugin(): PluginOption {
  return viteMockServe({
    // 扫描根目录：src/api 下所有 .mock.ts（含子目录，支持项目级隔离）
    mockPath: 'src/api',
    // 必须用 ignore 函数：仅 .mock.ts/.mock.js 视为 mock；否则递归编译业务 .ts 会触发 @ 别名解析失败
    ignore: (fileName: string) => !/\.mock\.(ts|js)$/.test(fileName),
    // DEV HMR 文件监听
    watchFiles: true,
    // 控制台打印匹配日志，便于调试
    logger: true,
    cors: true,
  })
}
