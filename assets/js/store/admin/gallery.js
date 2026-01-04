import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import adminGalleryApi from '../../api/admin/gallery.js'

export const useAdminGalleryStore = defineStore('adminGallery', () => {
  const pendingGalleries = ref([])
  const isLoading = ref(false)

  async function loadPendingGalleries() {
    isLoading.value = true
    try {
      pendingGalleries.value = await adminGalleryApi.getPendingGalleries()
    } catch (e) {
      console.error('Failed to load pending galleries:', e)
    } finally {
      isLoading.value = false
    }
  }

  async function approveGallery(id) {
    await adminGalleryApi.approveGallery(id)
    pendingGalleries.value = pendingGalleries.value.filter((g) => g.id !== id)
  }

  async function rejectGallery(id) {
    await adminGalleryApi.rejectGallery(id)
    pendingGalleries.value = pendingGalleries.value.filter((g) => g.id !== id)
  }

  return {
    pendingGalleries: readonly(pendingGalleries),
    isLoading: readonly(isLoading),
    loadPendingGalleries,
    approveGallery,
    rejectGallery
  }
})
