import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceAgendaApi from '../../api/bandSpace/band-space-agenda.js'

export const useBandAgendaStore = defineStore('bandAgenda', () => {
  const items = ref([])
  const isLoading = ref(false)
  const isRefreshing = ref(false)
  const isSaving = ref(false)
  const isDeleting = ref(false)
  const loadError = ref(null)

  let requestId = 0
  // The period the view last asked for. Calling fetchAgenda without one reuses it, so the refetch
  // that follows every mutation reloads what the user is looking at. Letting the API fall back to
  // its own window (today + 30 days) instead would wipe the items of a month the user navigated
  // to, and the agenda would look empty even though the save went through.
  let currentRange = { from: undefined, to: undefined }

  async function fetchAgenda(bandSpaceId, range) {
    const { from, to } = range ?? currentRange
    currentRange = { from, to }
    const currentRequestId = ++requestId
    isLoading.value = items.value.length === 0
    isRefreshing.value = true
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
        isRefreshing.value = false
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

  async function deleteOccurrence(bandSpaceId, entryId, occurrenceDate) {
    isDeleting.value = true
    try {
      await bandSpaceAgendaApi.deleteOccurrence(bandSpaceId, entryId, occurrenceDate)
      await fetchAgenda(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  async function deleteFromOccurrence(bandSpaceId, entryId, occurrenceDate) {
    isDeleting.value = true
    try {
      await bandSpaceAgendaApi.deleteFromOccurrence(bandSpaceId, entryId, occurrenceDate)
      await fetchAgenda(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  function clear() {
    items.value = []
    loadError.value = null
    currentRange = { from: undefined, to: undefined }
  }

  return {
    items: readonly(items),
    isLoading: readonly(isLoading),
    isRefreshing: readonly(isRefreshing),
    isSaving: readonly(isSaving),
    isDeleting: readonly(isDeleting),
    loadError: readonly(loadError),
    fetchAgenda,
    createEntry,
    updateEntry,
    deleteEntry,
    deleteOccurrence,
    deleteFromOccurrence,
    clear
  }
})
