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

  const searchResults = ref([])
  const isSearching = ref(false)

  async function searchPublications(term) {
    if (!term || term.trim().length < 3) {
      searchResults.value = []
      return
    }
    isSearching.value = true
    try {
      searchResults.value = await adminPublicationApi.searchPublications(term.trim())
    } catch (e) {
      console.error('Failed to search publications:', e)
      searchResults.value = []
    } finally {
      isSearching.value = false
    }
  }

  async function deletePublication(id) {
    await adminPublicationApi.deletePublication(id)
    searchResults.value = searchResults.value.filter((p) => p.id !== id)
    pendingPublications.value = pendingPublications.value.filter((p) => p.id !== id)
  }

  function clearSearch() {
    searchResults.value = []
  }

  return {
    pendingPublications: readonly(pendingPublications),
    isLoading: readonly(isLoading),
    searchResults: readonly(searchResults),
    isSearching: readonly(isSearching),
    loadPendingPublications,
    approvePublication,
    rejectPublication,
    searchPublications,
    deletePublication,
    clearSearch
  }
})
