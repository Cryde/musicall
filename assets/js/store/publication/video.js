import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import videoApi from '../../api/publication/video.js'

export const useVideoStore = defineStore('video', () => {
  const isModalOpen = ref(false)
  const isLoadingPreview = ref(false)
  const isLoadingAdd = ref(false)
  const preview = ref(null)
  const previewError = ref(null)

  function openModal() {
    isModalOpen.value = true
  }

  function closeModal() {
    isModalOpen.value = false
    resetState()
  }

  function resetState() {
    preview.value = null
    previewError.value = null
    isLoadingPreview.value = false
    isLoadingAdd.value = false
  }

  async function fetchPreview(url) {
    isLoadingPreview.value = true
    preview.value = null
    previewError.value = null

    try {
      preview.value = await videoApi.getPreview(url)
    } catch (error) {
      previewError.value = error
      throw error
    } finally {
      isLoadingPreview.value = false
    }
  }

  async function addVideo({ url, title, description, categoryId = null }) {
    isLoadingAdd.value = true

    try {
      const result = await videoApi.addVideo({ url, title, description, categoryId })
      return result
    } finally {
      isLoadingAdd.value = false
    }
  }

  return {
    isModalOpen: readonly(isModalOpen),
    isLoadingPreview: readonly(isLoadingPreview),
    isLoadingAdd: readonly(isLoadingAdd),
    preview: readonly(preview),
    previewError: readonly(previewError),
    openModal,
    closeModal,
    resetState,
    fetchPreview,
    addVideo
  }
})
