import axios from 'axios';

export default {
  login(username, password) {
    return axios.post(Routing.generate('api_login_check'), {username, password})
    .then(resp => resp.data);
  },
  refreshToken(refreshToken) {
    const formData = new FormData();
    formData.append('refresh_token', refreshToken);

    return axios.post(Routing.generate('gesdinet_jwt_refresh_token'), formData)
    .then(resp => resp.data);
  }
};