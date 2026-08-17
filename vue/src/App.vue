<template>
  <!-- 路由视图：通过 transition 实现页面切换淡入淡出效果 -->
  <router-view v-slot="{ Component }">
    <transition name="fade" mode="out-in">
      <component :is="Component" />
    </transition>
  </router-view>

  <!-- 全局骨架屏：接口请求进行中自动全屏显示（计数器见 utils/loading.ts） -->
  <AppSkeleton />
</template>

<script setup lang="ts">
  import AppSkeleton from '@/components/AppSkeleton.vue'
</script>

<style scoped lang="scss">
  // 路由页面淡入淡出动画
  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.2s ease;
  }

  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
  }
</style>
