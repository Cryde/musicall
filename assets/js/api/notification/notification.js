/** global: Routing */

import axios from 'axios'

export default {
  getNotifications() {
    return axios.get(Routing.generate('api_notifications_get'))
      .then(resp => resp.data)
  }
}
