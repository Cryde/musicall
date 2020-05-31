/** global: Routing */

import axios from 'axios';

export default {
  searchByTerm(term) {
    return axios.get(Routing.generate('api_search', {term}))
    .then(resp => resp.data);
  },
  searchUsers(search) {
    return axios.get(Routing.generate('api_user_search', {search}))
    .then(resp => resp.data);
  }
}