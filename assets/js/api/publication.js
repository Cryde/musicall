/** global: Routing */

import axios from 'axios';

export default {
  getPublication(slug) {
    return axios.get(Routing.generate('api_publications_show', {slug}))
    .then(resp => resp.data)
  },
  getPreviewVideo(videoUrl) {
    return axios.post(Routing.generate('api_publications_video_preview'), {videoUrl})
    .then(resp => resp.data)
  },
  addVideo(payload) {
    return axios.post(Routing.generate('api_user_publication_add_video'), {...payload})
    .then(resp => resp.data)
  }
}