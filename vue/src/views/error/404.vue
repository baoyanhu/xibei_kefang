<template>
  <div class="not-found">
    <!-- 404 图标 -->
    <div class="not-found__icon">404</div>
    <!-- 提示文本 -->
    <div class="not-found__text">页面不存在</div>
  </div>
</template>

<script setup lang="ts">
  /**
   * 404 占位页
   * - 当路由未匹配到任何页面时展示
   * - 保持纯静态，禁止发任何 API 请求
   */
</script>

<style scoped lang="scss">
  .not-found {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    background: #f5f5f5;

    &__icon {
      font-size: 128px;
      font-weight: 700;
      margin-bottom: 32px;
      color: #999;
    }

    &__text {
      font-size: 32px;
      color: #666;
    }
  }
</style>
