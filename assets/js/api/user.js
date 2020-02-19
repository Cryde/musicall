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
  }
}