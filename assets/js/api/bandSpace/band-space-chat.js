/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * The whole Hydra body, not just `member`: the store needs `totalItems` to know whether there is
   * older history left to load.
   */
  getMessages(bandSpaceId, { page } = {}) {
    return axios
      .get(Routing.generate('api_band_space_chat_messages_get_collection', { bandSpaceId }), {
        params: { page }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * The channel's pinned messages, newest pin first. Its own collection because a pinned message is
   * nearly always far up the history, so the pane has not loaded the page it lives on (#969).
   */
  getPinnedMessages(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_chat_pinned_messages_get_collection', { bandSpaceId }))
      .then((resp) => resp.data.member)
      .catch(handleApiError)
  },

  /** Both answer with the message as it now stands, so the pane never has to guess what changed. */
  pinMessage(bandSpaceId, messageId) {
    return axios
      .post(Routing.generate('api_band_space_chat_messages_pin', { bandSpaceId, id: messageId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  unpinMessage(bandSpaceId, messageId) {
    return axios
      .delete(
        Routing.generate('api_band_space_chat_messages_unpin', { bandSpaceId, id: messageId })
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Turns a message into a task (#979). Nothing to send: the title and the description are worked
   * out from the stored message, server side, because that is the only place its plain text exists.
   * Answers with the created task.
   */
  createTaskFromMessage(bandSpaceId, messageId) {
    return axios
      .post(
        Routing.generate('api_band_space_chat_messages_task_post', { bandSpaceId, id: messageId })
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  markAsRead(bandSpaceId) {
    return axios
      .post(Routing.generate('api_band_space_chat_read', { bandSpaceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * `attachments` are the synthetic `<type>-<uuid>` identifiers the search endpoint handed out, which
   * is exactly what ChatMessageCreate accepts (#970): nothing is translated on the way.
   */
  postMessage(bandSpaceId, content, attachments = []) {
    return axios
      .post(
        Routing.generate('api_band_space_chat_messages_post', { bandSpaceId }),
        { content, attachments },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Answers with the whole message, reactions included, so the pill row can be re-read from it. */
  addReaction(bandSpaceId, messageId, emoji) {
    return axios
      .post(
        Routing.generate('api_band_space_chat_message_reactions_post', {
          bandSpaceId,
          id: messageId
        }),
        { emoji },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  removeReaction(bandSpaceId, messageId, emoji) {
    return axios
      .delete(
        Routing.generate('api_band_space_chat_message_reactions_delete', {
          bandSpaceId,
          id: messageId,
          emoji
        })
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** The stored `@[uuid]` format, the same thing postMessage sends, never what is on screen. */
  updateMessage(bandSpaceId, messageId, content) {
    return axios
      .patch(
        Routing.generate('api_band_space_chat_messages_patch', { bandSpaceId, id: messageId }),
        { content },
        {
          headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** 204 with no body: the message stays in the list as a tombstone. */
  deleteMessage(bandSpaceId, messageId) {
    return axios
      .delete(
        Routing.generate('api_band_space_chat_messages_delete', { bandSpaceId, id: messageId })
      )
      .catch(handleApiError)
  }
}
