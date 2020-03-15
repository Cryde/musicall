export default {
  changePassword({oldPassword, newPassword}) {
    return fetch(Routing.generate('api_user_change_password'), {
      method: 'POST',
      body: JSON.stringify({oldPassword, newPassword}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(async (resp) => {
      const json = await resp.json();

      return resp.ok ? json : Promise.reject(json);
    })
  },
  requestResetPassword(login) {
    return fetch(Routing.generate('api_user_request_reset_password'), {
      method: 'POST',
      body: JSON.stringify({login}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(async (resp) => {
      const json = await resp.json();

      return resp.ok ? json : Promise.reject(json);
    })
  },
  resetPassword({token, password}) {
    return fetch(Routing.generate('api_user_reset_password', {token}), {
      method: 'POST',
      body: JSON.stringify({password}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(async (resp) => {
      const json = await resp.json();

      return resp.ok ? json : Promise.reject(json);
    })
  }
}