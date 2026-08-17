/**
 * 750 设计稿 px → vw 转换配置
 * - 将 class / scss 中书写的 px 值按 750 设计稿基准自动转换为 vw
 * - inline :style、JS 动态值、组件 size prop 等场景由 utils/vw.ts 负责
 */
module.exports = {
  plugins: {
    'postcss-px-to-viewport-8-plugin': {
      // 需要转换的单位
      unitToConvert: 'px',
      // 设计稿宽度基准：750px（iPhone 双倍稿）
      viewportWidth: 750,
      // 转换后 vw 小数精度
      unitPrecision: 5,
      // 需要转换的属性列表，* 表示全部
      propList: ['*'],
      // 转换后的视口单位
      viewportUnit: 'vw',
      // 字体使用的视口单位
      fontViewportUnit: 'vw',
      // 选择器黑名单：命中该类名时不转换
      selectorBlackList: ['.no-vw'],
      // 最小转换像素值，小于 1px 不转换
      minPixelValue: 1,
      // 是否转换媒体查询中的 px
      mediaQuery: false,
      // 是否直接替换原属性值（true）或追加新属性（false）
      replace: true,
      // 排除 node_modules 中的第三方样式，保持其原始 px
      exclude: [/node_modules/],
      // 是否生成横屏适配样式
      landscape: false,
    },
  },
}
