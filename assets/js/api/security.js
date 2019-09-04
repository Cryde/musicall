export default {
  login(username, password) {
    return fetch(Routing.generate('api_login'), {
      method: 'POST',
      body: JSON.stringify({username, password}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
};