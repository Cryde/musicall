/** global: Routing */

import axios from 'axios';

export default {
  getLastAnnounces() {
    return axios.get(Routing.generate('api_musician_announces_get_last_collection'))
    .then(resp => resp.data);
  },
}