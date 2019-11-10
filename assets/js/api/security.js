/** global: Routing */

export default {
  login(username, password) {
    return fetch(Routing.generate('api_login_check'), {
      method: 'POST',
      body: JSON.stringify({username, password}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then((resp) => {
      if (resp.status === 401) {
        throw new Error('Login ou mot de passe incorrect');
      }
      return resp;
    })
    .then(resp => resp.json());
  },
  refreshToken(refreshToken) {
    const formData = new FormData();
    formData.append('refresh_token', refreshToken);

    return fetch(Routing.generate('gesdinet_jwt_refresh_token'), {
      method: 'POST',
      body: formData
    })
    .then(resp => resp.json());
  }
};