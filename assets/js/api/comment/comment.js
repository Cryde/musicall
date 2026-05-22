/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getThread(threadId) {
    return axios
      .get(Routing.generate('api_comment_threads_get_item', { id: threadId }))
      .then((resp) => resp.data)
  },

  getComments(threadId) {
    return axios
      .get(Routing.generate('api_comments_get_collection', { thread: threadId }))
      .then((resp) => resp.data)
  },

  postComment({ threadId, content, parentId = null }) {
    const payload = {
      thread: `/api/comment_threads/${threadId}`,
      content
    }
    if (parentId !== null && parentId !== undefined) {
      payload.parentId = parentId
    }
    return axios
      .post(Routing.generate('api_comments_post_collection'), payload, {
        headers: {
          'Content-Type': 'application/ld+json',
          Accept: 'application/ld+json'
        }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  voteComment(id, value) {
    return axios
      .post(
        Routing.generate('api_comment_vote_post', { id }),
        { user_vote: value },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  }
}
