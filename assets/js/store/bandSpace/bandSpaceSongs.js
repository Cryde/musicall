import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceSongsApi from '../../api/bandSpace/band-space-songs.js'

export const useBandSongsStore = defineStore('bandSongs', () => {
  const songs = ref([])
  const isLoading = ref(false)
  const loadError = ref(null)

  let songsRequestId = 0

  async function fetchSongs(bandSpaceId, { includeArchived = false } = {}) {
    const requestId = ++songsRequestId
    isLoading.value = songs.value.length === 0
    loadError.value = null

    try {
      const result = await bandSpaceSongsApi.getSongs(bandSpaceId, { includeArchived })
      if (requestId !== songsRequestId) return
      songs.value = result
    } catch (e) {
      if (requestId !== songsRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === songsRequestId) {
        isLoading.value = false
      }
    }
  }

  async function createSong(bandSpaceId, data) {
    const created = await bandSpaceSongsApi.createSong(bandSpaceId, data)
    songs.value = [...songs.value, created].sort((a, b) => a.title.localeCompare(b.title))
    return created
  }

  async function updateSong(bandSpaceId, songId, data) {
    const updated = await bandSpaceSongsApi.updateSong(bandSpaceId, songId, data)
    songs.value = songs.value
      .map((s) => (s.id === songId ? updated : s))
      .sort((a, b) => a.title.localeCompare(b.title))
    return updated
  }

  async function deleteSong(bandSpaceId, songId) {
    await bandSpaceSongsApi.deleteSong(bandSpaceId, songId)
    songs.value = songs.value.filter((s) => s.id !== songId)
  }

  function clear() {
    songs.value = []
    isLoading.value = false
    loadError.value = null
  }

  return {
    songs: readonly(songs),
    isLoading: readonly(isLoading),
    loadError: readonly(loadError),
    fetchSongs,
    createSong,
    updateSong,
    deleteSong,
    clear
  }
})
