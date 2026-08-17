/**
 * 组件自动注册
 * - VantResolver: Vant 组件 + 图标自动注册
 * - 自定义组件(src/components/)手动 import(dirs: [])
 */
import Components from 'unplugin-vue-components/vite'
import { VantResolver } from 'unplugin-vue-components/resolvers'

export default function () {
  return Components({
    resolvers: [VantResolver()],
    dts: 'types/components.d.ts',
    dirs: [],
    directoryAsNamespace: false,
  })
}
