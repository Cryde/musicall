import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceSetlistsApi from '../../api/bandSpace/band-space-setlists.js'

export const useBandSetlistsStore = defineStore('bandSetlists', () => {
  const setlists = ref([])
  const isLoading = ref(false)
  const loadError = ref(null)

  let setlistsRequestId = 0

  async function fetchSetlists(bandSpaceId) {
    const requestId = ++setlistsRequestId
    isLoading.value = setlists.value.length === 0
    loadError.value = null

    try {
      const result = await bandSpaceSetlistsApi.getSetlists(bandSpaceId)
      if (requestId !== setlistsRequestId) return
      setlists.value = result
    } catch (e) {
      if (requestId !== setlistsRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === setlistsRequestId) {
        isLoading.value = false
      }
    }
  }

  function clear() {
    setlists.value = []
    isLoading.value = false
    loadError.value = null
  }

  return {
    setlists: readonly(setlists),
    isLoading: readonly(isLoading),
    loadError: readonly(loadError),
    fetchSetlists,
    clear
  }
})
