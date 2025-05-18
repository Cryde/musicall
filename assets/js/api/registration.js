/** global: Routing */

import axios from 'axios';

export default {
  register({username, email, password}) {
    return axios.post(Routing.generate('api_users_register'), {username, email, password},{
      headers: {'Content-Type': 'application/ld+json'}
    })
    .then(resp => resp.data);
  }
};