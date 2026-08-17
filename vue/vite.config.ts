/**
 * Vite 构建配置
 * - 环境变量加载 + 插件装配 + 路径别名 + 代理 + 分包
 */

import { defineConfig, loadEnv } from 'vite'
import { fileURLToPath, URL } from 'node:url'

import { createVitePlugins } from './vite/plugins'

export default defineConfig(({ command, mode }) => {
  // 按当前 mode 加载 .env 文件中的环境变量
  const env = loadEnv(mode, process.cwd())
  // 是否为构建（含 build:test），用于控制压缩插件等仅在构建时启用
  const isBuild = command === 'build'

  return {
    // 静态资源基础路径：构建产物用相对路径，便于部署到任意目录；开发服务器用根路径
    base: isBuild ? './' : '/',
    // 装配 Vite 插件（vue、Remix Icon 按需、压缩等）
    plugins: createVitePlugins(env, isBuild),
    resolve: {
      alias: {
        // 路径别名 @ 指向 src 目录，简化业务代码 import 路径
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
      // import 时可省略的扩展名，与 TS paths 保持一致
      extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json', '.vue'],
    },
    server: {
      // 开发服务器端口
      port: 5174,
      // 监听所有网络接口，方便局域网真机调试
      host: '0.0.0.0',
      // API 代理，把 /api 请求转发到后端 PHP 开发服务（php think run 127.0.0.1:8080），避免 CORS 问题
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
        },
      },
    },
    build: {
      // 默认关闭 sourcemap；需要调试时手动改为 true 或 'hidden'
      sourcemap: false,
      // 合并为单个 CSS 文件：按需样式跟随页面 chunk 会拆出大量 <1KB 碎 CSS（实测 22 个），
      // 每个碎文件一个请求，不如全量一次性加载（全站 CSS 总量小，预加载是净赚）
      cssCodeSplit: false,
      rollupOptions: {
        output: {
          // 按依赖包拆分 chunk，提升缓存命中率并控制单文件体积
          manualChunks(id: string): string | undefined {
            if (!id.includes('node_modules')) return undefined
            if (id.includes('echarts') || id.includes('zrender')) return 'echarts'
            if (id.includes('vant')) return 'vant'
            // vue-router 同样命中 'vue'，并入 vue-core（与框架同生命周期，不值得单独 chunk）
            if (id.includes('vue')) return 'vue-core'
            // pinia 等其余依赖落入 vendor，不再产生 <10KB 微型 chunk
            return 'vendor'
          },
        },
      },
    },
  }
})
