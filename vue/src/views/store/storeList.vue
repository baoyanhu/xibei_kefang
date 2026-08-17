<!--
  门店列表页（C 端）
  - 调用 POST /api/store/list（后端只返回营业中门店）
  - 门店名称模糊搜索 / 下拉刷新 / loading-empty-error 三态
-->
<template>
  <div class="store-list-page">
    <!-- 顶部导航 -->
    <van-nav-bar title="门店列表" />

    <!-- 搜索栏：门店名称模糊筛选（回车触发） -->
    <van-search v-model="keyword" placeholder="请输入门店名称" @search="handleSearch" />

    <!-- 内容区：下拉刷新包裹三态 -->
    <van-pull-refresh v-model="isRefreshing" @refresh="handleRefresh">
      <!-- 加载态：骨架屏 -->
      <div v-if="isLoading" class="skeleton-wrap">
        <van-skeleton v-for="n in 3" :key="n" title :row="2" />
      </div>

      <!-- 错误态：错误图 + 重试按钮 -->
      <div v-else-if="errorMsg !== ''" class="error-wrap">
        <van-empty image="error" :description="errorMsg" />
        <van-button size="small" plain type="primary" @click="loadData()">重新加载</van-button>
      </div>

      <!-- 空态 -->
      <van-empty v-else-if="count === 0" description="暂无门店" />

      <!-- 正常态：计数 + 门店列表 -->
      <template v-else>
        <!-- 计数条 -->
        <div class="count-bar">共 {{ count }} 家门店</div>
        <!-- 门店列表 -->
        <van-cell-group inset>
          <van-cell
            v-for="item in storeList"
            :key="item.id"
            :title="item.store_name"
            :label="item.store_code"
          >
            <!-- 营业状态标签 -->
            <template #value>
              <van-tag type="success">
                {{ item.business_status === 1 ? '营业中' : '休息中' }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>
      </template>
    </van-pull-refresh>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted } from 'vue'
  import { fetchStoreList } from '@/api/store'

  // 搜索关键词（门店名称模糊筛选，对应后端入参 store_name）
  const keyword = ref('')

  // 门店列表数据
  const storeList = ref<any[]>([])

  // 门店总数（后端返回 count）
  const count = ref(0)

  // 加载态（首屏/搜索展示骨架屏）
  const isLoading = ref(true)

  // 错误信息（非空即进入错误态）
  const errorMsg = ref('')

  // 下拉刷新进行中
  const isRefreshing = ref(false)

  /**
   * 加载门店列表
   * @param showSkeleton - 是否展示骨架屏（下拉刷新自带指示器，不闪骨架屏）
   */
  async function loadData(showSkeleton = true) {
    if (showSkeleton) {
      isLoading.value = true
    }
    errorMsg.value = ''
    try {
      const { count: total, list }: any = await fetchStoreList({
        store_name: keyword.value.trim(),
      })
      count.value = total ?? 0
      storeList.value = list ?? []
    } catch (err: any) {
      // 500 场景拦截器已 Toast，这里落错误态供重试
      errorMsg.value = err?.message || '加载失败，请重试'
    } finally {
      isLoading.value = false
    }
  }

  /** 搜索（van-search 回车触发） */
  function handleSearch() {
    loadData()
  }

  /** 下拉刷新 */
  async function handleRefresh() {
    try {
      await loadData(false)
    } finally {
      isRefreshing.value = false
    }
  }

  // 首屏加载
  onMounted(() => loadData())
</script>

<style scoped lang="scss">
  .store-list-page {
    min-height: 100vh;
    background: var(--page-bg, #f7f8fa);
  }

  .skeleton-wrap {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap, 21px);
    padding: var(--page-padding-x, 28px);
  }

  .error-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--gap-block, 17px);
    padding: var(--card-gap, 21px) 0;
  }

  .count-bar {
    padding: var(--head-gap, 17px) var(--page-padding-x, 28px) var(--gap-atomic, 7px);
    font-size: var(--fs-aux, 21px);
    color: var(--ink-tertiary, #969799);
  }
</style>
