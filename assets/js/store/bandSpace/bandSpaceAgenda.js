import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceAgendaApi from '../../api/bandSpace/band-space-agenda.js'

export const useBandAgendaStore = defineStore('bandAgenda', () => {
  const items = ref([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const isDeleting = ref(false)
  const loadError = ref(null)

  let requestId = 0

  async function fetchAgenda(bandSpaceId, { from, to } = {}) {
    const currentRequestId = ++requestId
    isLoading.value = items.value.length === 0
    loadError.value = null
    try {
      const data = await bandSpaceAgendaApi.getAgenda(bandSpaceId, { from, to })
      if (currentRequestId !== requestId) return
      items.value = data
    } catch (error) {
      if (currentRequestId !== requestId) return
      loadError.value = error?.message ?? "Erreur lors du chargement de l'agenda"
    } finally {
      if (currentRequestId === requestId) {
        isLoading.value = false
      }
    }
  }

  async function createEntry(bandSpaceId, data) {
    isSaving.value = true
    try {
      const created = await bandSpaceAgendaApi.createEntry(bandSpaceId, data)
      await fetchAgenda(bandSpaceId)
      return created
    } finally {
      isSaving.value = false
    }
  }

  async function updateEntry(bandSpaceId, entryId, data) {
    isSaving.value = true
    try {
      const updated = await bandSpaceAgendaApi.updateEntry(bandSpaceId, entryId, data)
      await fetchAgenda(bandSpaceId)
      return updated
    } finally {
      isSaving.value = false
    }
  }

  async function deleteEntry(bandSpaceId, entryId) {
    isDeleting.value = true
    try {
      await bandSpaceAgendaApi.deleteEntry(bandSpaceId, entryId)
      await fetchAgenda(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  function clear() {
    items.value = []
    loadError.value = null
  }

  return {
    items: readonly(items),
    isLoading: readonly(isLoading),
    isSaving: readonly(isSaving),
    isDeleting: readonly(isDeleting),
    loadError: readonly(loadError),
    fetchAgenda,
    createEntry,
    updateEntry,
    deleteEntry,
    clear
  }
})
