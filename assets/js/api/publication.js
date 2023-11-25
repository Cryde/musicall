/** global: Routing */

import axios from 'axios';

export default {
  getPublication(slug) {
    return axios.get(Routing.generate('api_publication_get_item', {slug}))
    .then(resp => resp.data)
  },
  getPreviewVideo(videoUrl) {
    return axios.post(Routing.generate('api_publications_video_preview'), {videoUrl})
    .then(resp => resp.data)
  },
  addVideo(payload) {
    return axios.post(Routing.generate('api_publication_video_add'), {...payload}, {
      headers: {
        'Content-Type': 'application/ld+json',
      }
    })
    .then(resp => resp.data)
  }
}