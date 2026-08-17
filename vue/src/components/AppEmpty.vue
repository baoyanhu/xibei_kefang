<!--
  AppEmpty 空态/错误态（框架级，基于 van-empty）
  - variant=page：整页状态面板（白卡 + 204px 插图 + 主/副文案 + error 时重试按钮）
  - variant=block：列表/模块内空态（无卡片，插图 + 一行文案）；⚠️ block 不支持 error/desc，
    传入会被忽略（模块加载失败请用 toast 提示或 page 形态）
  - error=true 时 page 形态显示「重新加载」按钮，点击 emit retry
  - 全部样式自持于本组件 scoped（值照抄原型 .state-panel/.empty-block），
    复制到其它项目即工作，不依赖 theme.scss 的全局类
-->
<template>
  <!-- 整页状态面板 -->
  <div v-if="variant === 'page'" class="ae-panel">
    <van-empty class="ae-empty">
      <template #image>
        <img :src="image" alt="" class="ae-img" />
      </template>
      <template #description>
        <div class="ae-text">
          {{ textTitle }}
          <template v-if="textDesc">
            <br />
            <span class="ae-sub">{{ textDesc }}</span>
          </template>
        </div>
      </template>
    </van-empty>
    <!-- 错误态重试按钮 -->
    <van-button v-if="error" class="ae-btn" @click="handleRetry">重新加载</van-button>
  </div>

  <!-- 列表/模块内空态 -->
  <van-empty v-else class="ae-block">
    <template #image>
      <img :src="image" alt="" class="ae-img" />
    </template>
    <template #description>
      <div class="ae-btext">{{ textTitle }}</div>
    </template>
  </van-empty>
</template>

<script setup lang="ts">
  import { computed } from 'vue'

  import defaultImage from '@/assets/images/empty-state@2x.png'

  /**
   * AppEmpty 空态/错误态
   * @prop title - 主文案（默认「暂无数据」；error 且未传时默认「数据加载失败」）
   * @prop desc - 副文案（仅 page 形态；error 且未传时默认「网络异常，请稍后重试」）
   * @prop image - 插图地址，默认内置 empty-state@2x.png
   * @prop variant - page=整页面板 / block=列表区块内（block 不支持 error/desc，传入无效）
   * @prop error - 错误态（仅 page 形态生效，显示「重新加载」按钮）
   * @emits retry - 点击「重新加载」
   */
  const props = withDefaults(
    defineProps<{
      title?: string
      desc?: string
      image?: string
      variant?: 'page' | 'block'
      error?: boolean
    }>(),
    {
      title: '',
      desc: '',
      image: defaultImage,
      variant: 'page',
      error: false,
    },
  )

  const emit = defineEmits<{ (e: 'retry'): void }>()

  // 主文案：未传时按 error 与否给默认
  const textTitle = computed(() => props.title || (props.error ? '数据加载失败' : '暂无数据'))
  // 副文案：error 且未传时给默认，普通空态未传则无副文案
  const textDesc = computed(() => props.desc || (props.error ? '网络异常，请稍后重试' : ''))

  /** 点击重新加载，交回页面重新取数 */
  function handleRetry() {
    emit('retry')
  }
</script>

<style scoped>
  /* ===== 整页形态（值照抄原型 .state-panel） ===== */
  .ae-panel {
    margin: 0 28px 21px;
    padding: 56px 28px;
    text-align: center;
    background: var(--card-bg, #ffffff);
    border-radius: 24px;
    box-shadow: var(--card-shadow, 0 2px 12px rgba(17, 24, 39, 0.06));
  }
  .ae-text {
    font-size: 27px;
    color: var(--ink-secondary, #4a4a4a);
    margin-top: 21px;
  }
  /* 副文案：24px 三级灰 */
  .ae-sub {
    font-size: 24px;
    color: var(--ink-tertiary, #969799);
  }
  /* 重试按钮：品牌渐变（scoped 的 data 属性选择器特异性 0-2-0，压得住 Vant 默认白底） */
  .ae-btn {
    margin-top: 28px;
    padding: 14px 35px;
    border: none;
    border-radius: 28px;
    background: var(--brand-grad, linear-gradient(135deg, #4a63da, #6d74dc));
    color: #fff; /* 原型字面值（按钮白字，无对应 token） */
    font-size: 26px;
    font-weight: 600;
  }
  .ae-btn:active {
    opacity: 0.9;
  }

  /* ===== 模块内形态（值照抄原型 .empty-block） ===== */
  .ae-block {
    padding: 35px 0 28px;
    text-align: center;
  }
  .ae-btext {
    font-size: 24px;
    color: var(--ink-tertiary, #969799);
    margin-top: 17px;
  }

  /* 两形态共用：204px 居中插图 + van-empty 容器归零 */
  .ae-img {
    width: 204px;
    height: auto;
    display: block;
    margin: 0 auto;
  }
  /* page 形态的 van-empty 容器归零（内边距由 .ae-panel 提供；.ae-block 的 35px 内边距保留） */
  .ae-empty {
    padding: 0;
  }
  .ae-empty:deep(.van-empty__image),
  .ae-block:deep(.van-empty__image) {
    width: 204px;
    height: auto;
  }
  .ae-empty:deep(.van-empty__description),
  .ae-block:deep(.van-empty__description) {
    margin-top: 0;
    padding: 0;
  }
</style>
