import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceAbsenceApi from '../../api/bandSpace/band-space-absence.js'

export const useBandAbsenceStore = defineStore('bandAbsence', () => {
  const absences = ref([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const isRefreshing = ref(false)
  const loadError = ref(null)

  let requestId = 0
  // The period the drawer last asked for. Every mutation refetches it rather than the API's own
  // default window, so saving while looking at next year does not swap the list for the next 30 days.
  let currentRange = { from: undefined, to: undefined }

  async function fetchAbsences(bandSpaceId, range) {
    const { from, to } = range ?? currentRange
    currentRange = { from, to }
    const currentRequestId = ++requestId
    // A refetch with rows already on screen keeps them there: the full spinner is for the first
    // load only, so reopening the drawer does not blank a list it is about to redraw identically.
    isLoading.value = absences.value.length === 0
    isRefreshing.value = true
    loadError.value = null
    try {
      const data = await bandSpaceAbsenceApi.getAbsences(bandSpaceId, { from, to })
      // A slower earlier request must not overwrite a newer one: switching the year selector twice
      // in a row would otherwise settle on the first year asked for.
      if (currentRequestId !== requestId) return
      absences.value = data
    } catch (error) {
      if (currentRequestId !== requestId) return
      loadError.value = error?.message ?? 'Erreur lors du chargement des indisponibilités'
    } finally {
      if (currentRequestId === requestId) {
        isLoading.value = false
        isRefreshing.value = false
      }
    }
  }

  async function createAbsence(bandSpaceId, data) {
    isSaving.value = true
    try {
      const created = await bandSpaceAbsenceApi.createAbsence(bandSpaceId, data)
      await fetchAbsences(bandSpaceId)
      return created
    } finally {
      isSaving.value = false
    }
  }

  async function updateAbsence(bandSpaceId, absenceId, data) {
    isSaving.value = true
    try {
      const updated = await bandSpaceAbsenceApi.updateAbsence(bandSpaceId, absenceId, data)
      await fetchAbsences(bandSpaceId)
      return updated
    } finally {
      isSaving.value = false
    }
  }

  async function deleteAbsence(bandSpaceId, absenceId) {
    await bandSpaceAbsenceApi.deleteAbsence(bandSpaceId, absenceId)
    await fetchAbsences(bandSpaceId)
  }

  function clear() {
    absences.value = []
    loadError.value = null
    currentRange = { from: undefined, to: undefined }
  }

  return {
    absences: readonly(absences),
    isLoading: readonly(isLoading),
    isSaving: readonly(isSaving),
    isRefreshing: readonly(isRefreshing),
    loadError: readonly(loadError),
    fetchAbsences,
    createAbsence,
    updateAbsence,
    deleteAbsence,
    clear
  }
})
