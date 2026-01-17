/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getPreferences() {
    return axios
      .get(Routing.generate('api_user_notification_preferences_get'))
      .then((resp) => resp.data)
  },

  updatePreferences(data) {
    return axios
      .patch(
        Routing.generate('api_user_notification_preferences_edit'),
        data,
        {
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
