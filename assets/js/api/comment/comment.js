/** global: Routing */

import axios from 'axios';

export default {
  postComment({threadId, content}) {
    return axios.post(Routing.generate('api_thread_comments_post', {id: threadId}), {content})
    .then(resp => resp.data);
  },
  getThread({threadId}) {
    return axios.get(Routing.generate('api_thread_comments_list', {id: threadId}))
    .then(resp => resp.data);
  }
}