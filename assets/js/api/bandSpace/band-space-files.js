/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getFiles(
    bandSpaceId,
    { folderId, tagId, source, taskId, financeEntryId, query, mime, uploaderId, sort, order } = {}
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
  }
}
