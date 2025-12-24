import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceApi from '../../api/bandSpace/band-space.js'

export const useBandSpaceStore = defineStore('bandSpaces', () => {
  const spaces = ref([])
  const isLoading = ref(false)
  const isCreateModalOpen = ref(false)
  const isCreating = ref(false)

  async function loadMyBandSpaces() {
    isLoading.value = true
    try {
      spaces.value = await bandSpaceApi.getMyBandSpace()
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
    return spaces.value.find(s => s.id === id) || null
  }

  function openCreateModal() {
    isCreateModalOpen.value = true
  }

  function closeCreateModal() {
    isCreateModalOpen.value = false
  }

  function clear() {
    spaces.value = []
  }

  return {
    loadMyBandSpaces,
    createBandSpace,
    getById,
    openCreateModal,
    closeCreateModal,
    clear,
    spaces: readonly(spaces),
    isLoading: readonly(isLoading),
    isCreateModalOpen: readonly(isCreateModalOpen),
    isCreating: readonly(isCreating)
  }
})
