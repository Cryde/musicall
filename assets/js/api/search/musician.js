/** global: Routing */

import axios from 'axios';

export default {
  getResults({longitude, latitude, instrument, styles, type}) {
    return axios.post(Routing.generate('api_search_musician'), {longitude, latitude, instrument, styles, type})
    .then(resp => resp.data);
  },
  getResultsFromText({search}) {
    return axios.get(Routing.generate('api_musician_announces_search_collection', {search}))
    .then(resp => resp.data);
  }
}