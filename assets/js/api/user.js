/** global: Routing */

import axios from 'axios';

export default {
  changePassword({oldPassword, newPassword}) {
    return axios.post(Routing.generate('api_users_change_password_post'), {oldPassword, newPassword},{
      headers: {'Content-Type': 'application/ld+json'}
    })
    .then(resp => resp.data);
  },
  requestResetPassword(login) {
    return axios.post(Routing.generate('api_users_request_reset_password'), {login},{
      headers: {'Content-Type': 'application/ld+json'}
    })
    .then(resp => resp.data);
  },
  resetPassword({token, password}) {
    return axios.post(Routing.generate('api_users_reset_password', {token}), {password},{
      headers: {'Content-Type': 'application/ld+json'}
    })
    .then(resp => resp.data);
  },
  me() {
    return axios.get(Routing.generate('api_users_get_self'))
    .then(resp => resp.data);
  },
  changePicture(form) {
    return axios.post(Routing.generate('api_user_profile_picture_post'), form);
  }
}