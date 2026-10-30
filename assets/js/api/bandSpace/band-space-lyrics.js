/** global: Routing */

import axios from 'axios'
import { filenameFromContentDisposition } from '../../utils/downloadBlob.js'
import { handleApiError } from '../utils/handleApiError.js'

const JSON_LD = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }

/** Lyrics on their own (#1260): written before their song exists, linked to it later. */
export default {
  /**
   * @param {boolean} archived true lists the trash instead of the live lyrics
   */
  getLyricsList(bandSpaceId, { archived = false } = {}) {
    const url = Routing.generate('api_band_space_lyrics_get_collection', { bandSpaceId })
    return axios
      .get(archived ? `${url}?archived=true` : url)
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  getLyrics(bandSpaceId, lyricsId) {
    return axios
      .get(Routing.generate('api_band_space_lyrics_get_item', { bandSpaceId, id: lyricsId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** @param {{title: string, content?: string, song_id?: string}} data */
  createLyrics(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_lyrics_post', { bandSpaceId }), data, {
        headers: JSON_LD
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * `{title}` alone renames. `{content, expected_version}` saves the text: `expected_version` is the
   * revision the member started from, so a save over someone else's newer one is refused with a 409.
   */
  updateLyrics(bandSpaceId, lyricsId, data) {
    return axios
      .patch(Routing.generate('api_band_space_lyrics_patch', { bandSpaceId, id: lyricsId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Rewrites the chords, and the key of the linked song if there is one. */
  transposeLyrics(bandSpaceId, lyricsId, semitones, expectedVersion) {
    return axios
      .post(
        Routing.generate('api_band_space_lyrics_transpose', { bandSpaceId, id: lyricsId }),
        { semitones, expected_version: expectedVersion },
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  linkLyrics(bandSpaceId, lyricsId, songId) {
    return axios
      .post(
        Routing.generate('api_band_space_lyrics_link', { bandSpaceId, id: lyricsId }),
        { song_id: songId },
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  unlinkLyrics(bandSpaceId, lyricsId) {
    return axios
      .post(
        Routing.generate('api_band_space_lyrics_unlink', { bandSpaceId, id: lyricsId }),
        {},
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Soft delete: the lyrics move to the trash, unlinked. */
  deleteLyrics(bandSpaceId, lyricsId) {
    return axios
      .delete(Routing.generate('api_band_space_lyrics_delete', { bandSpaceId, id: lyricsId }))
      .catch(handleApiError)
  },

  restoreLyrics(bandSpaceId, lyricsId) {
    return axios
      .post(
        Routing.generate('api_band_space_lyrics_restore', { bandSpaceId, id: lyricsId }),
        {},
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Fetched as a blob for the reason the setlist export gives, see band-space-setlists.js. */
  downloadPdf(bandSpaceId, lyricsId, { chords = true, singers = true, transpose = 0 } = {}) {
    const params = new URLSearchParams({
      chords: chords ? '1' : '0',
      singers: singers ? '1' : '0',
      transpose: String(transpose)
    })
    const url = `${Routing.generate('api_band_space_lyrics_pdf_export', { bandSpaceId, id: lyricsId })}?${params}`
    return axios.get(url, { responseType: 'blob' }).then((resp) => ({
      blob: resp.data,
      filename: filenameFromContentDisposition(resp.headers['content-disposition'])
    }))
  }
}
