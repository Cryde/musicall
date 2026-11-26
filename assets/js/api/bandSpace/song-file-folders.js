/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

const JSON_LD = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }

/** Folders inside a song's files (#1451). Which folder a file sits in is on the file itself. */
export default {
  getSongFolders(bandSpaceId, songId) {
    return axios
      .get(
        Routing.generate('api_band_space_song_file_folders_get_song_collection', {
          bandSpaceId,
          songId
        })
      )
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  /** Every song's folders: the Files tree, and the names already used in the space. */
  getSpaceSongFolders(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_song_file_folders_get_collection', { bandSpaceId }))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  createFolder(bandSpaceId, songId, { name, parentId = null }) {
    return axios
      .post(
        Routing.generate('api_band_space_song_file_folders_post', { bandSpaceId, songId }),
        { name, parentId },
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  renameFolder(bandSpaceId, songId, folderId, name) {
    return axios
      .patch(
        Routing.generate('api_band_space_song_file_folders_patch', {
          bandSpaceId,
          songId,
          id: folderId
        }),
        { name },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteFolder(bandSpaceId, songId, folderId) {
    return axios
      .delete(
        Routing.generate('api_band_space_song_file_folders_delete', {
          bandSpaceId,
          songId,
          id: folderId
        })
      )
      .catch(handleApiError)
  },

  /** Null puts the file back at the song's root. */
  moveFile(bandSpaceId, songId, fileId, folderId) {
    return axios
      .post(
        Routing.generate('api_band_space_song_files_move', { bandSpaceId, songId, fileId }),
        { folderId },
        { headers: JSON_LD }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
