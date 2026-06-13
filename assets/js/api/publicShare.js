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

  // The password is never put in the URL (query strings leak via logs, history
  // and Referer). It is sent as the X-Share-Password header in PublicShare.vue.
  buildDownloadUrl(token) {
    return Routing.generate('api_band_space_file_shares_public_download', { token })
  }
}
