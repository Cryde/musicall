/** global: Routing */

import axios from 'axios';

export default {
  add({type, note, styles, instrument, locationName, longitude, latitude}) {
    return axios.post(Routing.generate('api_musician_announces_post'), {
          type,
          note,
          styles,
          instrument,
          locationName,
          longitude,
          latitude
        },
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  },
  getByCurrentUser() {
    return axios.get(Routing.generate('api_musician_announces_get_self_collection'))
    .then(resp => resp.data);
  },
  getLastAnnounces() {
    return axios.get(Routing.generate('api_musician_announces_get_last_collection'))
    .then(resp => resp.data);
  },
  delete(id) {
    return axios.delete(Routing.generate('api_musician_announces_delete', {id}))
    .then(resp => resp.data)
  }
}