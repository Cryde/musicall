import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceActivityApi from '../../api/bandSpace/band-space-activity.js'

export const useBandSpaceActivityStore = defineStore('bandSpaceActivity', () => {
  const items = ref([])
  const totalItems = ref(0)
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const currentPage = ref(1)

  const filters = ref({
    modules: [],
    actorId: null,
    type: null,
    from: null,
    to: null
  })

  async function load(bandSpaceId) {
    isLoading.value = true
    try {
      currentPage.value = 1
      const data = await bandSpaceActivityApi.list(bandSpaceId, {
        ...filters.value,
        page: 1
      })
      items.value = data.member
      totalItems.value = data.totalItems ?? 0
    } finally {
      isLoading.value = false
    }
  }

  async function loadMore(bandSpaceId) {
    if (isLoadingMore.value || items.value.length >= totalItems.value) {
      return
    }

    isLoadingMore.value = true
    try {
      currentPage.value += 1
      const data = await bandSpaceActivityApi.list(bandSpaceId, {
        ...filters.value,
        page: currentPage.value
      })
      items.value = [...items.value, ...data.member]
      totalItems.value = data.totalItems ?? totalItems.value
    } finally {
      isLoadingMore.value = false
    }
  }

  function setFilters(newFilters) {
    filters.value = { ...filters.value, ...newFilters }
  }

  function clear() {
    items.value = []
    totalItems.value = 0
    currentPage.value = 1
    filters.value = { modules: [], actorId: null, type: null, from: null, to: null }
  }

  return {
    items: readonly(items),
    totalItems: readonly(totalItems),
    isLoading: readonly(isLoading),
    isLoadingMore: readonly(isLoadingMore),
    filters,
    load,
    loadMore,
    setFilters,
    clear
  }
})
