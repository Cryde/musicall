import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import musicianAnnounceApi from '../../api/announce/musician.js'

export const useMusicianAnnounceStore = defineStore('musicianAnnounce', () => {
  const lastAnnounces = ref([])
  const isLoadingLast = ref(false)
  let latestRequest = 0

  // Only the latest request lands, list and loading flag alike: flipping the homepage filters quickly
  // must not leave an earlier filter's list on screen, nor stop the spinner while the last one is out.
  async function loadLastAnnounces(type = null) {
    const request = ++latestRequest
    isLoadingLast.value = true
    try {
      const { member } = await musicianAnnounceApi.getLastAnnounces(type)
      if (request === latestRequest) lastAnnounces.value = member
    } finally {
      if (request === latestRequest) isLoadingLast.value = false
    }
  }

  function clear() {
    latestRequest++
    lastAnnounces.value = []
    isLoadingLast.value = false
  }

  return {
    loadLastAnnounces,
    clear,
    lastAnnounces: readonly(lastAnnounces),
    isLoadingLast: readonly(isLoadingLast)
  }
})
