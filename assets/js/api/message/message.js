/** global: Routing */

import axios from 'axios'

export default {
  /**
   * Send a new message to a user (creates a new thread if needed)
   */
  postMessage({ recipientId, content }) {
    return axios
      .post(
        Routing.generate('api_message_post_to_user'),
        {
          recipient: `/api/users/${recipientId}`,
          content
        },
        {
          headers: { 'Content-Type': 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
  },

  /**
   * Post a message in an existing thread
   */
  postMessageInThread({ threadId, content }) {
    return axios
      .post(
        Routing.generate('api_message_post'),
        {
          content,
          thread: `/api/message_threads/${threadId}`
        },
        {
          headers: { 'Content-Type': 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
  },

  /**
   * Get all thread metas for the current user
   */
  getThreads() {
    return axios
      .get(Routing.generate('api_message_thread_meta_get_collection'))
      .then((resp) => resp.data)
  },

  /**
   * Get a page of messages for a thread, newest first. Page 1 is the newest, so paging up is paging
   * backwards through the conversation.
   */
  getMessages({ threadId, page = 1 }) {
    return axios
      .get(Routing.generate('api_message_get_collection', { threadId }), {
        // `order[id]` is the tiebreak, not a preference: the timestamp is second granular, and a tied
        // row with no total order can land on both sides of a page boundary or on neither.
        params: { 'order[creation_datetime]': 'desc', 'order[id]': 'desc', page }
      })
      .then((resp) => resp.data)
  },

  /**
   * Mark a thread as read
   */
  markThreadAsRead({ threadMetaId }) {
    return axios
      .patch(
        Routing.generate('api_message_thread_meta_patch', { id: threadMetaId }),
        {
          is_read: true
        },
        {
          headers: { 'Content-Type': 'application/merge-patch+json' }
        }
      )
      .then((resp) => resp.data)
  }
}
