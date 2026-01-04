import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import adminPublicationApi from '../../api/admin/publication.js'

export const useAdminPublicationStore = defineStore('adminPublication', () => {
  const pendingPublications = ref([])
  const isLoading = ref(false)

  async function loadPendingPublications() {
    isLoading.value = true
    try {
      pendingPublications.value = await adminPublicationApi.getPendingPublications()
    } catch (e) {
      console.error('Failed to load pending publications:', e)
    } finally {
      isLoading.value = false
    }
  }

  async function approvePublication(id) {
    await adminPublicationApi.approvePublication(id)
    pendingPublications.value = pendingPublications.value.filter((p) => p.id !== id)
  }

  async function rejectPublication(id) {
    await adminPublicationApi.rejectPublication(id)
    pendingPublications.value = pendingPublications.value.filter((p) => p.id !== id)
  }

  return {
    pendingPublications: readonly(pendingPublications),
    isLoading: readonly(isLoading),
    loadPendingPublications,
    approvePublication,
    rejectPublication
  }
})
