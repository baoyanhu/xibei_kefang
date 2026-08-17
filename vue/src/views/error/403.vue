<template>
  <div class="no-permission">
    <!-- 无权限图标 -->
    <div class="no-permission__icon">⚠️</div>
    <!-- 无权限提示文本 -->
    <div class="no-permission__text">无权限访问</div>
  </div>
</template>

<script setup lang="ts">
  /**
   * 无权限占位页
   * - 当 token 为空或后端返回 403 时跳转至此页
   * - 保持纯静态，禁止发任何 API 请求，避免与拦截器死循环
   */
</script>

<style scoped lang="scss">
  .no-permission {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    background: #f5f5f5;

    &__icon {
      font-size: 128px;
      margin-bottom: 32px;
    }

    &__text {
      font-size: 32px;
      color: #666;
    }
  }
</style>
