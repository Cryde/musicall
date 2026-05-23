import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceApi from '../../api/bandSpace/band-space.js'

export const useBandSpaceStore = defineStore('bandSpaces', () => {
  const spaces = ref([])
  const isLoading = ref(false)
  const isCreateModalOpen = ref(false)
  const isCreating = ref(false)
  const error = ref(null)

  // Computed getters for derived state
  const hasSpaces = computed(() => spaces.value.length > 0)
  const spacesCount = computed(() => spaces.value.length)

  // Map for O(1) lookup by ID
  const spacesMap = computed(() => new Map(spaces.value.map((s) => [s.id, s])))

  // Monotonic token to discard stale responses if loadMyBandSpaces is called
  // multiple times in quick succession (auth state churn, retries).
  let loadToken = 0

  async function loadMyBandSpaces() {
    const token = ++loadToken
    isLoading.value = true
    error.value = null

    try {
      const data = await bandSpaceApi.getMyBandSpaces()
      if (token !== loadToken) return
      spaces.value = data
    } catch (e) {
      if (token !== loadToken) return
      error.value = e.message || 'Failed to load band spaces'
      spaces.value = []
      throw e
    } finally {
      if (token === loadToken) {
        isLoading.value = false
      }
    }
  }

  async function createBandSpace(name) {
    isCreating.value = true

    try {
      const newSpace = await bandSpaceApi.create(name)
      spaces.value = [newSpace, ...spaces.value]
      return newSpace
    } finally {
      isCreating.value = false
    }
  }

  function getById(id) {
    return spacesMap.value.get(id) || null
  }

  function openCreateModal() {
    isCreateModalOpen.value = true
  }

  function closeCreateModal() {
    isCreateModalOpen.value = false
  }

  function clearError() {
    error.value = null
  }

  function clear() {
    spaces.value = []
    error.value = null
  }

  return {
    // Actions
    loadMyBandSpaces,
    createBandSpace,
    getById,
    openCreateModal,
    closeCreateModal,
    clearError,
    clear,
    // State (readonly)
    spaces: readonly(spaces),
    isLoading: readonly(isLoading),
    isCreateModalOpen: readonly(isCreateModalOpen),
    isCreating: readonly(isCreating),
    error: readonly(error),
    // Computed getters
    hasSpaces,
    spacesCount
  }
})
