/**
 * 门店模块接口（C 端）
 * - 门店列表：POST /api/store/list（业务 url 不带 /api 前缀，baseURL 已含）
 * - 后端 ApiAuth 中间件按 mch_id + employee_code 拉取 RMS 门店权限，接口只返回权限内营业中门店
 * - 鉴权参数在此统一注入（页面零感知）；DEV 值走 .env.development 的 VITE_MCH_ID / VITE_EMPLOYEE_CODE，
 *   真实项目接手后替换为登录流程写入的值
 */
import { request } from '@/utils/request'

interface postData {
  [key: string]: any
}

/** C 端公共鉴权参数（后端 ApiAuth 必传） */
const authParams = {
  mch_id: import.meta.env.VITE_MCH_ID ?? '',
  employee_code: import.meta.env.VITE_EMPLOYEE_CODE ?? '',
}

/** 门店列表（store_name 为门店名称模糊筛选） */
export function fetchStoreList(data: postData) {
  return request({ url: '/store/list', method: 'POST', data: { ...authParams, ...data } })
}
