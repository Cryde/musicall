/** global: Routing */

import axios from 'axios';

export default {
  add({type, note, styles, instrument, locationName, longitude, latitude}) {
    return axios.post(Routing.generate('api_musician_announce_add'), {
      type,
      note,
      styles,
      instrument,
      locationName,
      longitude,
      latitude
    })
    .then(resp => resp.data);
  },
  getByCurrentUser() {
    return axios.get(Routing.generate('api_user_announce_musician_list'))
    .then(resp => resp.data);
  }
}