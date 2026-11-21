import { computed, ref } from 'vue'
import bandSpaceFilesApi from '../api/bandSpace/band-space-files.js'
import { songsWithFiles } from '../utils/songFileCounts.js'

/**
 * The paperclip next to a song, for a whole list at once, and the songs « Chansons » unfolds into in
 * Files. itemsPerPage is the backend's paginationMaximumItemsPerPage (200), so songs whose files fall
 * past the first page still show it. Silent on failure: the count is informational.
 */
export function useSongFileCounts(bandSpaceId) {
  const songs = ref([])
  const counts = computed(() => new Map(songs.value.map((song) => [song.id, song.fileCount])))

  async function load() {
    try {
      const data = await bandSpaceFilesApi.getFiles(bandSpaceId(), {
        source: 'song',
        itemsPerPage: 200
      })
      songs.value = songsWithFiles(data.member ?? [])
    } catch {
      // The indicator is not worth an error.
    }
  }

  return { counts, songs, load }
}
