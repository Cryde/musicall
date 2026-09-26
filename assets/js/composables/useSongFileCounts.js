import { ref } from 'vue'
import bandSpaceFilesApi from '../api/bandSpace/band-space-files.js'
import { songFileCounts } from '../utils/songFileCounts.js'

/**
 * The paperclip next to a song, for a whole list at once. itemsPerPage is the backend's
 * paginationMaximumItemsPerPage (200), so songs whose files fall past the first page still show it.
 * Silent on failure: the count is informational.
 */
export function useSongFileCounts(bandSpaceId) {
  const counts = ref(new Map())

  async function load() {
    try {
      const data = await bandSpaceFilesApi.getFiles(bandSpaceId(), {
        source: 'song',
        itemsPerPage: 200
      })
      counts.value = songFileCounts(data.member ?? [])
    } catch {
      // The indicator is not worth an error.
    }
  }

  return { counts, load }
}
