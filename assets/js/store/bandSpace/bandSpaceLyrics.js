import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceLyricsApi from '../../api/bandSpace/band-space-lyrics.js'
import { byTitle, useBandSongsStore } from './bandSpaceSongs.js'

/** An item response as the list holds it: the list never carries the text. */
function asListItem({ content, version, singers, ...listed }) {
  return listed
}

/**
 * The band's lyrics (#1261), live and in the trash, as the setlist module's other stores keep songs
 * and setlists. The live list is also the index from a song to its lyrics, see lyricsList.js.
 */
export const useBandLyricsStore = defineStore('bandLyrics', () => {
  const lyrics = ref([])
  const archivedLyrics = ref([])
  const isLoading = ref(false)
  const isLoadingArchived = ref(false)
  const loadError = ref(null)

  let lyricsRequestId = 0
  let archivedRequestId = 0

  async function fetchLyrics(bandSpaceId) {
    const requestId = ++lyricsRequestId
    isLoading.value = lyrics.value.length === 0
    loadError.value = null

    try {
      const result = await bandSpaceLyricsApi.getLyricsList(bandSpaceId)
      if (requestId !== lyricsRequestId) return
      lyrics.value = result
    } catch (e) {
      if (requestId !== lyricsRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === lyricsRequestId) {
        isLoading.value = false
      }
    }
  }

  async function fetchArchivedLyrics(bandSpaceId) {
    const requestId = ++archivedRequestId
    isLoadingArchived.value = archivedLyrics.value.length === 0

    try {
      const result = await bandSpaceLyricsApi.getLyricsList(bandSpaceId, { archived: true })
      if (requestId !== archivedRequestId) return
      archivedLyrics.value = result
    } catch (e) {
      if (requestId !== archivedRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === archivedRequestId) {
        isLoadingArchived.value = false
      }
    }
  }

  function forSong(songId) {
    return lyrics.value.find((item) => item.song?.id === songId) ?? null
  }

  /** Into the live list or the trash, whichever the item now belongs to. */
  function applyItem(item) {
    const listed = asListItem(item)
    const others = (list) => list.filter((l) => l.id !== item.id)
    lyrics.value = others(lyrics.value)
    archivedLyrics.value = others(archivedLyrics.value)
    if (item.archive_datetime) {
      archivedLyrics.value = [...archivedLyrics.value, listed].sort(byTitle)
    } else {
      lyrics.value = [...lyrics.value, listed].sort(byTitle)
    }
  }

  /**
   * A lyrics write can change a song too: whether it has lyrics, or the key a transposition moved.
   * The write already succeeded, so a failed re-read is left to the next live refresh.
   */
  async function refreshSong(bandSpaceId, songId) {
    if (!songId) return
    await useBandSongsStore()
      .refreshSong(bandSpaceId, songId)
      .catch(() => null)
  }

  /** Someone else's newer version, after a conflict: it may have moved to the trash meanwhile. */
  async function reloadLyrics(bandSpaceId, lyricsId) {
    const latest = await bandSpaceLyricsApi.getLyrics(bandSpaceId, lyricsId)
    applyItem(latest)
    return latest
  }

  async function createLyrics(bandSpaceId, data) {
    const created = await bandSpaceLyricsApi.createLyrics(bandSpaceId, data)
    applyItem(created)
    await refreshSong(bandSpaceId, created.song?.id)
    return created
  }

  /** Only a song's has_lyrics can change with the text, and only when the text appears or goes. */
  async function updateLyrics(bandSpaceId, lyricsId, data) {
    const hadContent = lyrics.value.find((item) => item.id === lyricsId)?.has_content
    const updated = await bandSpaceLyricsApi.updateLyrics(bandSpaceId, lyricsId, data)
    applyItem(updated)
    if (updated.has_content !== hadContent) await refreshSong(bandSpaceId, updated.song?.id)
    return updated
  }

  async function transposeLyrics(bandSpaceId, lyricsId, semitones, expectedVersion) {
    const transposed = await bandSpaceLyricsApi.transposeLyrics(
      bandSpaceId,
      lyricsId,
      semitones,
      expectedVersion
    )
    applyItem(transposed)
    await refreshSong(bandSpaceId, transposed.song?.id)
    return transposed
  }

  async function linkLyrics(bandSpaceId, lyricsId, songId) {
    const linked = await bandSpaceLyricsApi.linkLyrics(bandSpaceId, lyricsId, songId)
    applyItem(linked)
    await refreshSong(bandSpaceId, songId)
    return linked
  }

  /** `songId` is the song they leave, taken from the caller's copy: the list may be older. */
  async function unlinkLyrics(bandSpaceId, lyricsId, songId) {
    const unlinked = await bandSpaceLyricsApi.unlinkLyrics(bandSpaceId, lyricsId)
    applyItem(unlinked)
    await refreshSong(bandSpaceId, songId)
    return unlinked
  }

  /** To the trash, which also unlinks them: the song they were on reads as without lyrics again. */
  async function archiveLyrics(bandSpaceId, lyricsId) {
    const archived = lyrics.value.find((item) => item.id === lyricsId)
    await bandSpaceLyricsApi.deleteLyrics(bandSpaceId, lyricsId)
    if (!archived) return
    applyItem({ ...archived, song: null, archive_datetime: new Date().toISOString() })
    await refreshSong(bandSpaceId, archived.song?.id)
  }

  async function restoreLyrics(bandSpaceId, lyricsId) {
    const restored = await bandSpaceLyricsApi.restoreLyrics(bandSpaceId, lyricsId)
    applyItem(restored)
    return restored
  }

  function clear() {
    lyrics.value = []
    archivedLyrics.value = []
    isLoading.value = false
    isLoadingArchived.value = false
    loadError.value = null
  }

  return {
    lyrics: readonly(lyrics),
    archivedLyrics: readonly(archivedLyrics),
    isLoading: readonly(isLoading),
    isLoadingArchived: readonly(isLoadingArchived),
    loadError: readonly(loadError),
    fetchLyrics,
    fetchArchivedLyrics,
    forSong,
    reloadLyrics,
    createLyrics,
    updateLyrics,
    transposeLyrics,
    linkLyrics,
    unlinkLyrics,
    archiveLyrics,
    restoreLyrics,
    clear
  }
})
