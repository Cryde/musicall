/** global: Routing */

import axios from 'axios';

export default {
  addStyle({name}) {
    return axios.post(Routing.generate('api_admin_attribute_style_add'), {name},
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  },
  addInstrument({name, musicianName}) {
    return axios.post(Routing.generate('api_admin_attribute_instrument_add'), {name, musicianName},
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  },
}