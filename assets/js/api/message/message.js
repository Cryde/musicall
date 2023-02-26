/** global: Routing */

import axios from 'axios';

export default {
  postMessage({recipientId, content}) {
    return axios.post(Routing.generate('api_message_add', {id: recipientId}), {content})
    .then(resp => resp.data);
  },
  postMessageInThread({threadId, content}) {
    return axios.post(Routing.generate('api_message_post'), {content, thread: `/api/message_threads/${threadId}`})
    .then(resp => resp.data);
  },
  getThreads() {
    return axios.get(Routing.generate('api_message_thread_meta_get_collection'))
    .then(resp => resp.data);
  },
  getMessages({threadId}) {
    const order = {creation_datetime: 'desc'};
    return axios.get(Routing.generate('api_message_get_collection', {threadId, order}))
    .then(resp => resp.data);
  },
  markThreadAsRead({threadMetaId}) {
    return axios.patch(Routing.generate('api_message_thread_meta_patch', {id: threadMetaId}), {is_read: true}, {headers: {'Content-Type': 'application/merge-patch+json'}})
    .then(resp => resp.data);
  }
}