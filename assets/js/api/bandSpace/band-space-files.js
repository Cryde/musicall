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
  }
}
