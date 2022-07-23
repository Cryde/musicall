/** global: Routing */

import axios from 'axios';

export default {
  listInstrument() {
    return axios.get(Routing.generate('api_instruments_get_collection'))
    .then(resp => resp.data)
    .then(resp => resp['hydra:member']);
  }
}