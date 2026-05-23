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

  // Single monotonic token bumped by every load* call. A stale page request
  // resolving after a fresh load/filter change is dropped on the floor so it
  // can't append to (or replace) the newer page set.
  let loadToken = 0

  async function load(bandSpaceId) {
    const token = ++loadToken
    isLoading.value = true
    try {
      currentPage.value = 1
      const data = await bandSpaceActivityApi.list(bandSpaceId, {
        ...filters.value,
        page: 1
      })
      if (token !== loadToken) return
      items.value = data.member
      totalItems.value = data.totalItems ?? 0
    } finally {
      if (token === loadToken) {
        isLoading.value = false
      }
    }
  }

  async function loadMore(bandSpaceId) {
    if (isLoadingMore.value || items.value.length >= totalItems.value) {
      return
    }

    const token = ++loadToken
    isLoadingMore.value = true
    try {
      currentPage.value += 1
      const data = await bandSpaceActivityApi.list(bandSpaceId, {
        ...filters.value,
        page: currentPage.value
      })
      if (token !== loadToken) return
      items.value = [...items.value, ...data.member]
      totalItems.value = data.totalItems ?? totalItems.value
    } finally {
      if (token === loadToken) {
        isLoadingMore.value = false
      }
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
