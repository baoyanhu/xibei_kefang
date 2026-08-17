/** * 首页（占位） */
<template>
  <div class="home-page">
    <van-cell-group inset title="门店">
      <van-cell title="门店列表" label="营业中门店（RMS 权限内）" is-link @click="$router.push({ name: 'StoreList' })" />
    </van-cell-group>
  </div>
</template>

<script setup lang="ts"></script>

<style scoped lang="scss">
  .home-page {
    min-height: 100vh;
    background: var(--van-background, #f3f3f3);
  }
</style>
