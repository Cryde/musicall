/** global: Routing */

import axios from 'axios';

export default {
  login(username, password) {
    return axios.post(Routing.generate('api_login_check'), {username, password})
    .then(resp => resp.data);
  },
  refreshToken() {
    return axios.get(Routing.generate('api_refresh_token'))
    .then(resp => resp.data);
  },
    logout() {
        return axios.post(Routing.generate('api_token_invalidate'))
        .then(resp => resp.data);
    }
};
