export default {
  getPublications(context) {
    return fetch(Routing.generate('api_user_publication_list'), {
      method: 'POST',
      body: JSON.stringify(context)
    })
    .then(resp => resp.json());
  },
  publishPublicationApi(id) {
    return fetch(Routing.generate('api_user_publication_publish', {id}))
    .then(handleErrors)
    .then(resp => resp.json());
  },
  deleteItem(id) {
    return fetch(Routing.generate('api_user_publication_delete', {id}))
  },
}

async function handleErrors(response) {
  if (!response.ok) {
    const data = await response.json();
    return Promise.reject(data)
  }
  return response;
}