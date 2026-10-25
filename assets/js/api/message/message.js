/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Send a new message to a user (creates a new thread if needed). `contactOrigin` says what it was
   * sent from (#998): `{ musicianAnnounceId }` for an announce, `{ fromTeacherProfile: true }` for
   * the recipient's teacher profile, or null.
   */
  postMessage({ recipientId, content, contactOrigin = null }) {
    return axios
      .post(
        Routing.generate('api_message_post_to_user'),
        {
          recipient: `/api/users/${recipientId}`,
          content,
          ...(contactOrigin ?? {})
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

  /** « I am writing » in a direct conversation (#1242). Nothing is stored, so nothing comes back. */
  sendTyping(threadId) {
    return axios.post(Routing.generate('api_message_thread_typing', { threadId }))
  },

  /**
   * « I have this conversation open », answered with whether the other person has too (#1246). `tab`
   * tells this page apart from the member's other tabs, as in the band chat.
   */
  beatPresence(threadId, tab) {
    return axios
      .post(Routing.generate('api_message_thread_presence', { threadId, tab }))
      .then((resp) => resp.data.online_user_ids ?? [])
      .catch(handleApiError)
  },

  /** A beacon, as the band chat's: this runs as the page goes, and the TTL catches what it misses. */
  leavePresence(threadId, tab) {
    const url = Routing.generate('api_message_thread_presence_leave', { threadId, tab })
    if (globalThis.navigator?.sendBeacon?.(url)) return
    axios.post(url).catch(() => {})
  },

  /** Rewrites one of your messages (#1245); answers with the message as the list shows it. */
  updateMessage({ threadId, messageId, content }) {
    return axios
      .patch(
        Routing.generate('api_message_thread_messages_patch', { threadId, id: messageId }),
        { content },
        {
          headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Leaves a tombstone in place of one of your messages (#1245). */
  deleteMessage({ threadId, messageId }) {
    return axios
      .delete(Routing.generate('api_message_thread_messages_delete', { threadId, id: messageId }))
      .catch(handleApiError)
  },

  addReaction({ threadId, messageId, emoji }) {
    return axios
      .post(
        Routing.generate('api_message_thread_message_reactions_post', { threadId, id: messageId }),
        { emoji },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  removeReaction({ threadId, messageId, emoji }) {
    return axios
      .delete(
        Routing.generate('api_message_thread_message_reactions_delete', {
          threadId,
          id: messageId,
          emoji
        })
      )
      .catch(handleApiError)
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
