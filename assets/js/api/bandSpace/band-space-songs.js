/** global: Routing */

import axios from 'axios'
import { filenameFromContentDisposition } from '../../utils/downloadBlob.js'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * @param {boolean} archived true lists the trash instead of the live repertoire
   */
  getSongs(bandSpaceId, { archived = false } = {}) {
    const url = Routing.generate('api_band_space_songs_get_collection', { bandSpaceId })
    return axios
      .get(archived ? `${url}?archived=true` : url)
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  getSong(bandSpaceId, songId) {
    return axios
      .get(Routing.generate('api_band_space_songs_get_item', { bandSpaceId, id: songId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createSong(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_songs_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateSong(bandSpaceId, songId, data) {
    return axios
      .patch(Routing.generate('api_band_space_songs_patch', { bandSpaceId, id: songId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Several titles to the trash at once (#1063). */
  archiveSongs(bandSpaceId, songIds) {
    return axios
      .post(
        Routing.generate('api_band_space_songs_archive_bulk', { bandSpaceId }),
        { song_ids: songIds },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  /** Soft delete: the title moves to the trash, it is not destroyed. */
  deleteSong(bandSpaceId, songId) {
    return axios
      .delete(Routing.generate('api_band_space_songs_delete', { bandSpaceId, id: songId }))
      .catch(handleApiError)
  },

  restoreSong(bandSpaceId, songId) {
    return axios
      .post(
        Routing.generate('api_band_space_songs_restore', { bandSpaceId, id: songId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** The song's lyrics in ChordPro, with the singers they name resolved (#1055). */
  getLyrics(bandSpaceId, songId) {
    return axios
      .get(Routing.generate('api_band_space_song_lyrics_get', { bandSpaceId, id: songId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * `expectedLyricsVersion` is the revision the member started from: a save over someone else's
   * newer one is refused with a 409 instead of wiping it.
   */
  updateLyrics(bandSpaceId, songId, lyrics, expectedLyricsVersion) {
    return axios
      .patch(
        Routing.generate('api_band_space_song_lyrics_patch', { bandSpaceId, id: songId }),
        { lyrics, expected_lyrics_version: expectedLyricsVersion },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Rewrites the chords and the song's key together. */
  transposeLyrics(bandSpaceId, songId, semitones, expectedLyricsVersion) {
    return axios
      .post(
        Routing.generate('api_band_space_song_lyrics_transpose', { bandSpaceId, id: songId }),
        { semitones, expected_lyrics_version: expectedLyricsVersion },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Fetched as a blob for the reason the setlist export gives, see band-space-setlists.js. */
  downloadPdf(bandSpaceId, songId, { chords = true, singers = true, transpose = 0 } = {}) {
    const params = new URLSearchParams({
      chords: chords ? '1' : '0',
      singers: singers ? '1' : '0',
      transpose: String(transpose)
    })
    const url = `${Routing.generate('api_band_space_songs_pdf_export', { bandSpaceId, id: songId })}?${params}`
    return axios.get(url, { responseType: 'blob' }).then((resp) => ({
      blob: resp.data,
      filename: filenameFromContentDisposition(resp.headers['content-disposition'])
    }))
  },

  getAttachedFiles(bandSpaceId, songId) {
    return axios
      .get(Routing.generate('api_band_space_song_files_get_collection', { bandSpaceId, songId }))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  uploadFile(bandSpaceId, songId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)
    return axios
      .post(
        Routing.generate('api_band_space_song_files_attach', { bandSpaceId, songId }),
        formData,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (progressEvent) => {
            if (onProgress && progressEvent.total) {
              const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              onProgress(percent)
            }
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  detachFile(bandSpaceId, songId, fileId, { archive = false } = {}) {
    const baseUrl = Routing.generate('api_band_space_song_files_detach', {
      bandSpaceId,
      songId,
      id: fileId
    })
    const url = archive ? `${baseUrl}?archive=true` : baseUrl
    return axios.delete(url).catch(handleApiError)
  }
}
