/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  sendCode(email) {
    return axios
      .post(
        Routing.generate('api_email_verify_send'),
        { email },
        {
          headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  checkCode(email, code) {
    return axios
      .post(
        Routing.generate('api_email_verify_check'),
        { email, code },
        {
          headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
