/** global: Routing */

import axios from 'axios';

export default {
  listInstrument() {
    return axios.get(Routing.generate('api_attributes_instruments'))
    .then(resp => resp.data);
  }
}