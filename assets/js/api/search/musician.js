/** global: Routing */

import axios from 'axios';

export default {
  getResults({longitude, latitude, instrument, styles, type}) {
    return axios.post(Routing.generate('api_search_musician'), {longitude, latitude, instrument, styles, type})
    .then(resp => resp.data);
  }
}