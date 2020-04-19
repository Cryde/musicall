import axios from 'axios';

export default {
  register({username, email, password}) {
    return axios.post(Routing.generate('api_register'), {username, email, password})
    .then(resp => resp.data);
  }
};