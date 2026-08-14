import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceSongsApi from '../../api/bandSpace/band-space-songs.js'

export const useBandSongsStore = defineStore('bandSongs', () => {
  const songs = ref([])
  // Two lists rather than one filtered list, because the repertoire table and the trash are separate
  // views and the sidebar shows the trash count while the repertoire is open. A band has a handful of
  // archived titles and the collection is unpaginated, so this costs one small request on module load.
  const archivedSongs = ref([])
  const isLoading = ref(false)
  const isLoadingArchived = ref(false)
  const loadError = ref(null)

  let songsRequestId = 0
  let archivedRequestId = 0

  async function fetchSongs(bandSpaceId) {
    const requestId = ++songsRequestId
    isLoading.value = songs.value.length === 0
    loadError.value = null

    try {
      const result = await bandSpaceSongsApi.getSongs(bandSpaceId)
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

  async function fetchArchivedSongs(bandSpaceId) {
    const requestId = ++archivedRequestId
    isLoadingArchived.value = archivedSongs.value.length === 0

    try {
      const result = await bandSpaceSongsApi.getSongs(bandSpaceId, { archived: true })
      if (requestId !== archivedRequestId) return
      archivedSongs.value = result
    } catch (e) {
      if (requestId !== archivedRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === archivedRequestId) {
        isLoadingArchived.value = false
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

  /**
   * Archiving moves the title from the repertoire to the trash rather than destroying it, so both
   * lists are updated: the trash entry is what the sidebar counts and what offers the way back.
   */
  async function deleteSong(bandSpaceId, songId) {
    await bandSpaceSongsApi.deleteSong(bandSpaceId, songId)
    const archived = songs.value.find((s) => s.id === songId)
    songs.value = songs.value.filter((s) => s.id !== songId)
    if (archived) {
      archivedSongs.value = [
        ...archivedSongs.value,
        { ...archived, archive_datetime: new Date().toISOString() }
      ].sort((a, b) => a.title.localeCompare(b.title))
    }
  }

  async function restoreSong(bandSpaceId, songId) {
    const restored = await bandSpaceSongsApi.restoreSong(bandSpaceId, songId)
    archivedSongs.value = archivedSongs.value.filter((s) => s.id !== songId)
    songs.value = [...songs.value, restored].sort((a, b) => a.title.localeCompare(b.title))
    return restored
  }

  function clear() {
    songs.value = []
    archivedSongs.value = []
    isLoading.value = false
    isLoadingArchived.value = false
    loadError.value = null
  }

  return {
    songs: readonly(songs),
    archivedSongs: readonly(archivedSongs),
    isLoading: readonly(isLoading),
    isLoadingArchived: readonly(isLoadingArchived),
    loadError: readonly(loadError),
    fetchSongs,
    fetchArchivedSongs,
    createSong,
    updateSong,
    deleteSong,
    restoreSong,
    clear
  }
})
