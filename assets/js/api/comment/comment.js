/** global: Routing */

import axios from 'axios';

export default {
  postComment(data) {
    return axios.post(Routing.generate('api_comments_post_collection'), data)
    .then(resp => resp.data);
  },
  getComments(filters) {
    return axios.get(Routing.generate('api_comments_get_collection', filters))
    .then(resp => resp.data);
  },
  getThread({threadId}) {
    return axios.get(Routing.generate('api_comment_threads_get_item', {id: threadId}))
    .then(resp => resp.data);
  }
}