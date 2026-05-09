/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  upload(bandSpaceId, noteId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)

    return axios
      .post(
        Routing.generate('api_band_space_note_files_attach', { bandSpaceId, noteId }),
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
        file: resp.data,
        quotaApproaching: resp.headers['x-quota-approaching'] === 'true'
      }))
      .catch(handleApiError)
  },

  detach(bandSpaceId, noteId, fileId) {
    return axios
      .delete(
        Routing.generate('api_band_space_note_files_detach', {
          bandSpaceId,
          noteId,
          id: fileId
        })
      )
      .catch(handleApiError)
  }
}
