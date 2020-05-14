/** global: Routing */

import axios from 'axios';

export default {
  searchByTerm(term) {
    return axios.get(Routing.generate('api_search', {term}))
    .then(resp => resp.data);
  }
}