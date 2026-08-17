/**
 * 路由配置
 * - 定义应用路由表，支持懒加载与页面元信息（meta.title）
 * - 预留业务模块路由聚合入口
 * - 全局前置守卫自动设置 document.title
 */

import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'

// 扩展 vue-router 的 RouteMeta 类型，使 meta.title 具备类型提示
declare module 'vue-router' {
  interface RouteMeta {
    // 页面标题，守卫会自动设置 document.title
    title?: string
  }
}

// 公共路由：无需权限即可访问的页面
const publicRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/home.vue'),
    meta: { title: '欢迎使用' },
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权限访问' },
  },
]

// 业务模块路由自动聚合：扫描 ./modules/*.ts，每个文件 default export RouteRecordRaw[]
// 约定：项目级路由文件落 router/modules/<projectName>.ts，新建文件即自动接入，无需手动改 index.ts
type RouteModule = { default: RouteRecordRaw[] }
const moduleFiles = import.meta.glob<RouteModule>('./modules/*.ts', { eager: true })
const moduleRoutes: RouteRecordRaw[] = Object.values(moduleFiles).flatMap((mod) => mod.default)

// 兜底路由：未匹配到任何路由时展示 404 页面
const fallbackRoute: RouteRecordRaw = {
  path: '/:pathMatch(.*)*',
  name: 'NotFound',
  component: () => import('@/views/error/404.vue'),
  meta: { title: '页面不存在' },
}

// 合并所有路由
const routes: RouteRecordRaw[] = [...publicRoutes, ...moduleRoutes, fallbackRoute]

// 创建路由实例
const router = createRouter({
  // Hash 模式：与生产 base './' 自洽，可部署到任意子目录且无需服务端 rewrite 配置
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes,
  // 切换路由后滚动到顶部
  scrollBehavior: () => ({ top: 0 }),
})

// 全局前置守卫：设置页面标题；登录/权限校验等业务逻辑可在此扩展
router.beforeEach((to, _from, next) => {
  // 路由未配置 title 时回退到 VITE_APP_TITLE
  document.title = String(to.meta.title || import.meta.env.VITE_APP_TITLE)
  next()
})

export default router
