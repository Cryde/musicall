export default {
  login(username, password) {
    return fetch(Routing.generate('api_login'), {
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
    .then(resp => resp.json())
  }
};