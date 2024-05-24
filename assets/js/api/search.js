/** global: Routing */

import axios from 'axios';

export default {
  searchByTerm(term) {
    return axios.get(Routing.generate('api_publication_search', {term}))
    .then(resp => resp.data)
    .then(resp => resp['hydra:member']);
  },
  searchUsers(search) {
    return axios.get(Routing.generate('api_user_search', {search}))
    .then(resp => resp.data);
  }
}