/**
 * vw 适配 helper
 * - 覆盖 postcss 够不着的 inline :style / JS 动态值 / 组件 size prop
 * - 按 750 设计稿基准将 px 转换为 vw 或真实像素
 */

/**
 * 设计稿 px 值 → vw 字符串
 * @param px - 设计稿标注的像素值
 * @returns 转换后的 vw 字符串
 */
export const vw = (px: number): string => `${+(px / 7.5).toFixed(5)}vw`

/**
 * 设计稿 px 值 → 当前屏幕真实像素数字
 * 仅用于 canvas / 手势等只认数字的场景
 * @param px - 设计稿标注的像素值
 * @returns 当前屏幕下对应的真实像素值
 */
export const realPx = (px: number): number => {
  // SSR 或无 window 场景下直接返回设计稿 px 值
  if (typeof window === 'undefined') return px
  return (window.innerWidth / 750) * px
}
