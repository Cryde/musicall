export default {
  register({username, email, password}) {
    return fetch(Routing.generate('api_register'), {
      method: 'POST',
      body: JSON.stringify({username, email, password})
    })
    .then(resp => resp.json());
  }
};