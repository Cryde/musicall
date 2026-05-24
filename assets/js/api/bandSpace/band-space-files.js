/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getFiles(
    bandSpaceId,
    {
      folderId,
      tagId,
      source,
      taskId,
      financeEntryId,
      query,
      mime,
      uploaderId,
      sort,
      order,
      itemsPerPage
    } = {}
  ) {
    const params = {}
    if (folderId) params.folder_id = folderId
    if (tagId) params.tag_id = tagId
    if (source) params.source = source
    if (taskId) params.task_id = taskId
    if (financeEntryId) params.finance_entry_id = financeEntryId
    if (query) params.query = query
    if (mime) params.mime = mime
    if (uploaderId) params.uploader_id = uploaderId
    if (sort) params.sort = sort
    if (order) params.order = order
    if (itemsPerPage) params.itemsPerPage = itemsPerPage

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

  deleteFile(bandSpaceId, fileId) {
    return axios
      .delete(Routing.generate('api_band_space_files_delete', { bandSpaceId, id: fileId }))
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

  uploadFile(bandSpaceId, { file, folderId, tagIds }, onProgress) {
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
        onUploadProgress: (progressEvent) => {
          if (onProgress && progressEvent.total) {
            const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percent)
          }
        }
      })
      .then((resp) => ({
        file: resp.data,
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
        version: resp.data,
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
    const route =
      sourceType === 'task'
        ? Routing.generate('api_band_space_task_files_get_collection', {
            bandSpaceId,
            taskId: sourceId
          })
        : Routing.generate('api_band_space_finance_entry_files_get_collection', {
            bandSpaceId,
            entryId: sourceId
          })

    return axios
      .get(route)
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  attachFileUpload(bandSpaceId, sourceType, sourceId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)

    const route =
      sourceType === 'task'
        ? Routing.generate('api_band_space_task_files_attach', { bandSpaceId, taskId: sourceId })
        : Routing.generate('api_band_space_finance_entry_files_attach', {
            bandSpaceId,
            entryId: sourceId
          })

    return axios
      .post(route, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          if (onProgress && progressEvent.total) {
            const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percent)
          }
        }
      })
      .then((resp) => ({
        file: resp.data,
        quotaApproaching: resp.headers['x-quota-approaching'] === 'true'
      }))
      .catch(handleApiError)
  },

  attachExistingFile(bandSpaceId, fileId, sourceType, sourceId) {
    return axios
      .post(
        Routing.generate('api_band_space_files_attach_existing', { bandSpaceId, fileId }),
        { sourceType, sourceId },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  detachFromSource(bandSpaceId, sourceType, sourceId, fileId) {
    const route =
      sourceType === 'task'
        ? Routing.generate('api_band_space_task_files_detach', {
            bandSpaceId,
            taskId: sourceId,
            id: fileId
          })
        : Routing.generate('api_band_space_finance_entry_files_detach', {
            bandSpaceId,
            entryId: sourceId,
            id: fileId
          })

    return axios.delete(route).catch(handleApiError)
  }
}
