/** global: Routing */

import axios from 'axios';

export default {
  searchByTerm(term) {
    return axios.get(Routing.generate('api_publication_search', {term}))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  },
  searchUsers(search) {
    return axios.get(Routing.generate('api_users_search', {search}))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  }
}