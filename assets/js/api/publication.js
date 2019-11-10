/** global: Routing */

export default {
  getPublication(slug) {
    return fetch(Routing.generate('api_publications_show', {slug}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  getPreviewVideo(videoUrl) {
    return fetch(Routing.generate('api_publications_video_preview'), {
      method: 'POST',
      body: JSON.stringify({videoUrl}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  addVideo(payload) {
    return fetch(Routing.generate('api_user_publication_add_video'), {
      method: 'POST',
      body: JSON.stringify({...payload}),
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}