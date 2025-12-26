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

  async function loadMyBandSpaces() {
    isLoading.value = true
    error.value = null

    try {
      spaces.value = await bandSpaceApi.getMyBandSpaces()
    } catch (e) {
      error.value = e.message || 'Failed to load band spaces'
      spaces.value = []
      throw e
    } finally {
      isLoading.value = false
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
