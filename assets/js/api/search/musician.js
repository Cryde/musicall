/** global: Routing */

import axios from 'axios';

export default {
  getResults({longitude, latitude, instrument, styles, type}) {
    return axios.post(Routing.generate('api_search_musician'), {longitude, latitude, instrument, styles, type})
    .then(resp => resp.data);
  },
  getResultsFromText({search}) {
    return axios.post(Routing.generate('api_search_musician_text'), {search})
    .then(resp => resp.data);
  }
}