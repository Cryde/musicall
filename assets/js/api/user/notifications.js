/** global: Routing */

import axios from 'axios';

export default {
  getNotifications() {
    return axios.get(Routing.generate('api_user_notifications'))
    .then(resp => resp.data)
    .then(resp => resp.data);
  }
}