import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import musicianAnnounceApi from '../../api/announce/musician.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'

export const useUserAnnounceStore = defineStore('userAnnounce', () => {
  const announces = ref([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const saveError = ref(null)

  async function loadAnnounces() {
    isLoading.value = true
    try {
      const data = await musicianAnnounceApi.getByCurrentUser()
      announces.value = data.member || []
    } catch (e) {
      console.error('Failed to load user announces:', e)
      announces.value = []
    } finally {
      isLoading.value = false
    }
  }

  async function createAnnounce({ type, instrument, styles, location, note }) {
    isSaving.value = true
    saveError.value = null
    try {
      await musicianAnnounceApi.create({
        type: type === 'band' ? TYPES_ANNOUNCE_BAND : TYPES_ANNOUNCE_MUSICIAN,
        note: note || '',
        styles: styles.map((style) => `/api/styles/${style.id}`),
        instrument: `/api/instruments/${instrument.id}`,
        locationName: location.name,
        longitude: String(location.longitude),
        latitude: String(location.latitude)
      })
      await loadAnnounces()
      return true
    } catch (e) {
      try {
        handleApiError(e)
      } catch (normalizedError) {
        saveError.value = normalizedError.message
      }
      return false
    } finally {
      isSaving.value = false
    }
  }

  function clearError() {
    saveError.value = null
  }

  async function deleteAnnounce(id) {
    isLoading.value = true
    try {
      await musicianAnnounceApi.delete(id)
      await loadAnnounces()
      return true
    } catch (e) {
      console.error('Failed to delete announce:', e)
      return false
    } finally {
      isLoading.value = false
    }
  }

  function clear() {
    announces.value = []
    isLoading.value = false
    isSaving.value = false
    saveError.value = null
  }

  return {
    announces: readonly(announces),
    isLoading: readonly(isLoading),
    isSaving: readonly(isSaving),
    saveError: readonly(saveError),
    loadAnnounces,
    createAnnounce,
    deleteAnnounce,
    clearError,
    clear
  }
})
