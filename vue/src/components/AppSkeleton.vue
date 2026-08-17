<!--
  AppSkeleton 全局骨架屏（框架级）
  - 任何接口请求进行中自动全屏显示：request.ts 拦截器驱动计数器（见 utils/loading.ts）
  - 业务页面无需再手写 loading 骨架；分页/静默刷新/弹层取数在调用处传 { silent: true } 跳过
  - 版式为通用占位（hero + 四宫格 + 双卡片）；shimmer 动画自持于本组件 scoped
    （框架级组件复制到其它项目即工作，不依赖 theme.scss 的全局类）
-->
<template>
  <Teleport to="body">
    <Transition name="sk-fade">
      <!-- 全屏遮罩：盖住页面内容，z-index 走顶层（弹窗内提交也要盖住弹窗） -->
      <div v-if="loadingState.visible" class="app-skeleton">
        <!-- 通用占位版式（间距与卡片节奏对齐主流页面） -->
        <div class="sk-wrap">
          <div class="sk sk-hero"></div>
          <div class="sk-grid">
            <div v-for="i in 4" :key="i" class="sk sk-cell"></div>
          </div>
          <div class="sk sk-card"></div>
          <div class="sk sk-card"></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
  import { loadingState } from '@/utils/loading'
</script>

<style scoped>
  /* 全屏覆盖：页面底色，禁止穿透滚动内容露出 */
  .app-skeleton {
    position: fixed;
    inset: 0;
    z-index: var(--z-top);
    background: var(--page-bg, #f7f8fa);
    overflow: hidden;
  }
  .sk-wrap {
    padding: 0 28px;
  }
  /* 占位块：三段灰微光扫过 + 24px 圆角（值照抄 mvp-hifi 原型 .sk） */
  .sk {
    background: linear-gradient(90deg, #eef0f5 25%, #f8f9fc 50%, #eef0f5 75%);
    background-size: 200% 100%;
    animation: sk-shimmer 1.2s infinite;
    border-radius: 24px;
  }
  @keyframes sk-shimmer {
    0% {
      background-position: 200% 0;
    }
    100% {
      background-position: -200% 0;
    }
  }
  /* 占位块尺寸 */
  .sk-hero {
    height: 285px;
    margin-top: 21px;
  }
  .sk-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin: 21px 0;
  }
  .sk-cell {
    height: 145px;
  }
  .sk-card {
    height: 380px;
    margin-bottom: 21px;
  }
  /* 显隐淡入淡出，避免生硬跳变 */
  .sk-fade-enter-active,
  .sk-fade-leave-active {
    transition: opacity 0.15s ease;
  }
  .sk-fade-enter-from,
  .sk-fade-leave-to {
    opacity: 0;
  }
</style>
