/** global: Routing */

import axios from 'axios'
import { handleApiError } from './utils/handleApiError.js'

export default {
  getMetadata(token) {
    return axios
      .get(Routing.generate('api_band_space_file_shares_public_metadata', { token }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  buildDownloadUrl(token, password = null) {
    const base = Routing.generate('api_band_space_file_shares_public_download', { token })
    if (password) {
      const sep = base.includes('?') ? '&' : '?'
      return `${base}${sep}password=${encodeURIComponent(password)}`
    }
    return base
  }
}
