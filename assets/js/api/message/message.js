/** global: Routing */

import axios from 'axios';

export default {
  postMessage({recipientId, content}) {
    return axios.post(Routing.generate('api_message_add', {id: recipientId}), {content})
    .then(resp => resp.data);
  },
  postMessageInThread({threadId, content}) {
    return axios.post(Routing.generate('api_thread_message_add', {id: threadId}), {content})
    .then(resp => resp.data);
  },
  getThreads() {
    return axios.get(Routing.generate('api_thread_list'))
    .then(resp => resp.data);
  },
  getMessages({threadId}) {
    return axios.get(Routing.generate('api_thread_message_list', {id: threadId}))
    .then(resp => resp.data);
  },
  markThreadAsRead({threadId}) {
    return axios.patch(Routing.generate('api_thread_message_mark_read', {id: threadId}))
    .then(resp => resp.data);
  }
}