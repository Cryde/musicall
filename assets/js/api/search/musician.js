/** global: Routing */

import axios from 'axios';

export default {
  getResultsFromText({search}) {
    return axios.get(Routing.generate('api_musician_announces_search_collection', {search}))
    .then(resp => resp.data);
  }
}