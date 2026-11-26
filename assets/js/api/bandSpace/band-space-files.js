/** global: Routing */

import axios from 'axios'
import { assertUploadedFile } from '../../utils/fileUploadQueue.js'
import { handleApiError } from '../utils/handleApiError.js'

/** The routes of each thing a file can be attached to, and the name its id takes in them. */
const SOURCE_ROUTES = {
  task: { param: 'taskId', prefix: 'api_band_space_task_files' },
  finance: { param: 'entryId', prefix: 'api_band_space_finance_entry_files' },
  song: { param: 'songId', prefix: 'api_band_space_song_files' },
  setlist: { param: 'setlistId', prefix: 'api_band_space_setlist_files' }
}

const SOURCE_ACTIONS = { list: 'get_collection', attach: 'attach', detach: 'detach' }

function sourceRoute(sourceType, action, bandSpaceId, sourceId, extra = {}) {
  const source = SOURCE_ROUTES[sourceType]
  if (!source) throw new Error(`No attached files for « ${sourceType} »`)
  return Routing.generate(`${source.prefix}_${SOURCE_ACTIONS[action]}`, {
    bandSpaceId,
    [source.param]: sourceId,
    ...extra
  })
}

export default {
  getFiles(
    bandSpaceId,
    {
      folderId,
      tagId,
      source,
      taskId,
      financeEntryId,
      sourceId,
      songFolderId,
      query,
      mime,
      format,
      uploaderId,
      sort,
      order,
      page,
      itemsPerPage,
      archived
    } = {}
  ) {
    const params = {}
    if (folderId) params.folder_id = folderId
    if (tagId) params.tag_id = tagId
    if (source) params.source = source
    if (taskId) params.task_id = taskId
    if (financeEntryId) params.finance_entry_id = financeEntryId
    if (sourceId) params.source_id = sourceId
    if (songFolderId) params.song_folder_id = songFolderId
    if (query) params.query = query
    if (mime) params.mime = mime
    if (format) params.format = format
    if (uploaderId) params.uploader_id = uploaderId
    if (sort) params.sort = sort
    if (order) params.order = order
    if (page) params.page = page
    if (itemsPerPage) params.itemsPerPage = itemsPerPage
    if (archived) params.archived = 'true'

    return axios
      .get(Routing.generate('api_band_space_files_get_collection', { bandSpaceId }), { params })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  getFile(bandSpaceId, fileId) {
    return axios
      .get(Routing.generate('api_band_space_files_get_item', { bandSpaceId, id: fileId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateFile(bandSpaceId, fileId, data) {
    return axios
      .patch(Routing.generate('api_band_space_files_patch', { bandSpaceId, id: fileId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  restoreFile(bandSpaceId, fileId) {
    return axios
      .post(Routing.generate('api_band_space_files_restore', { bandSpaceId, id: fileId }), {})
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  // Destroys the file and its stored objects immediately, without waiting for the purge. Admin only.
  permanentDeleteFile(bandSpaceId, fileId) {
    return axios
      .delete(
        Routing.generate('api_band_space_files_permanent_delete', { bandSpaceId, id: fileId })
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteFile(bandSpaceId, fileId) {
    return axios
      .delete(Routing.generate('api_band_space_files_delete', { bandSpaceId, id: fileId }))
      .catch(handleApiError)
  },

  /**
   * The three bulk endpoints answer 204 with no body, so nothing is returned. A refusal names every
   * file that blocked the batch in its detail, which handleApiError puts on error.message.
   */
  bulkDeleteFiles(bandSpaceId, fileIds) {
    return axios
      .post(
        Routing.generate('api_band_space_files_bulk_delete', { bandSpaceId }),
        { file_ids: fileIds },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  bulkMoveFiles(bandSpaceId, fileIds, folderId) {
    return axios
      .post(
        Routing.generate('api_band_space_files_bulk_patch', { bandSpaceId }),
        { file_ids: fileIds, folder_id: folderId },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  bulkRestoreFiles(bandSpaceId, fileIds) {
    return axios
      .post(
        Routing.generate('api_band_space_files_bulk_restore', { bandSpaceId }),
        { file_ids: fileIds },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  getFileActivities(bandSpaceId, fileId) {
    return axios
      .get(
        Routing.generate('api_band_space_file_activities_get_collection', { bandSpaceId, fileId })
      )
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  getFolderTree(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_folders_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createFolder(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_folders_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateFolder(bandSpaceId, folderId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_folders_patch', { bandSpaceId, id: folderId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteFolder(bandSpaceId, folderId, { strategy = 'move_to_root' } = {}) {
    const baseUrl = Routing.generate('api_band_space_folders_delete', {
      bandSpaceId,
      id: folderId
    })
    const url = `${baseUrl}?strategy=${encodeURIComponent(strategy)}`
    return axios.delete(url).catch(handleApiError)
  },

  getTags(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_file_tags_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  getQuota(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_files_quota_get', { bandSpaceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  // `signal` lets a batch drop a file that is still going up when the member closes the dialog,
  // rather than spending their upstream on bytes nothing is waiting for any more.
  uploadFile(bandSpaceId, { file, folderId, tagIds }, onProgress, signal) {
    const formData = new FormData()
    formData.append('uploadedFile', file)
    if (folderId) formData.append('folderId', folderId)
    if (tagIds && tagIds.length > 0) {
      for (const tagId of tagIds) {
        formData.append('tagIds[]', tagId)
      }
    }

    return axios
      .post(Routing.generate('api_band_space_files_upload', { bandSpaceId }), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        signal,
        onUploadProgress: (progressEvent) => {
          if (onProgress && progressEvent.total) {
            const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percent)
          }
        }
      })
      .then((resp) => ({
        file: assertUploadedFile(resp.data),
        quotaApproaching: resp.headers['x-quota-approaching'] === 'true'
      }))
      .catch(handleApiError)
  },

  createTag(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_file_tags_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  getShares(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_file_shares_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createShare(bandSpaceId, fileId, data) {
    return axios
      .post(Routing.generate('api_band_space_file_shares_post', { bandSpaceId, fileId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  revokeShare(bandSpaceId, shareId) {
    return axios
      .delete(Routing.generate('api_band_space_file_shares_delete', { bandSpaceId, id: shareId }))
      .catch(handleApiError)
  },

  getVersions(bandSpaceId, fileId) {
    return axios
      .get(Routing.generate('api_band_space_file_versions_get_collection', { bandSpaceId, fileId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  uploadVersion(bandSpaceId, fileId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)

    return axios
      .post(
        Routing.generate('api_band_space_file_versions_upload', { bandSpaceId, fileId }),
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
      .then((resp) => ({
        version: assertUploadedFile(resp.data),
        quotaApproaching: resp.headers['x-quota-approaching'] === 'true'
      }))
      .catch(handleApiError)
  },

  rollbackVersion(bandSpaceId, fileId, versionNumber) {
    return axios
      .post(
        Routing.generate('api_band_space_file_versions_rollback', { bandSpaceId, fileId }),
        { versionNumber },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  getAttachedFiles(bandSpaceId, sourceType, sourceId) {
    return axios
      .get(sourceRoute(sourceType, 'list', bandSpaceId, sourceId))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  // `signal`, as for uploadFile: a batch closed halfway drops the file still going up. A song's file
  // can go straight into one of the song's folders (#1451).
  attachFileUpload(
    bandSpaceId,
    sourceType,
    sourceId,
    file,
    onProgress,
    signal,
    { songFolderId = null } = {}
  ) {
    const formData = new FormData()
    formData.append('uploadedFile', file)
    if (songFolderId) formData.append('songFolderId', songFolderId)

    return axios
      .post(sourceRoute(sourceType, 'attach', bandSpaceId, sourceId), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        signal,
        onUploadProgress: (progressEvent) => {
          if (onProgress && progressEvent.total) {
            const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percent)
          }
        }
      })
      .then((resp) => ({
        file: assertUploadedFile(resp.data),
        quotaApproaching: resp.headers['x-quota-approaching'] === 'true'
      }))
      .catch(handleApiError)
  },

  attachExistingFile(bandSpaceId, fileId, sourceType, sourceId, { songFolderId = null } = {}) {
    return axios
      .post(
        Routing.generate('api_band_space_files_attach_existing', { bandSpaceId, fileId }),
        { sourceType, sourceId, songFolderId },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  detachFromSource(bandSpaceId, sourceType, sourceId, fileId, { archive = false } = {}) {
    const baseUrl = sourceRoute(sourceType, 'detach', bandSpaceId, sourceId, { id: fileId })
    const url = archive ? `${baseUrl}?archive=true` : baseUrl

    return axios.delete(url).catch(handleApiError)
  }
}
