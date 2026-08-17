/**
 * 门店模块路由
 * - 门店列表页（落 modules/ 由 router/index.ts 自动聚合）
 */
import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/store/list',
    name: 'StoreList',
    component: () => import('@/views/store/storeList.vue'),
    meta: { title: '门店列表' },
  },
]

export default routes
