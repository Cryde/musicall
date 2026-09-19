import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceChatApi from '../../api/bandSpace/band-space-chat.js'
import {
  hasReacted,
  rolledBackReactions,
  toggledReactions
} from '../../utils/chatReactionToggle.js'
import {
  hasOlderToLoad,
  mergeMessages,
  nextOlderPageToLoad
} from '../../utils/messagePagination.js'
import { isForTheOpenConversation } from '../../utils/messageSignal.js'
import { useNotificationStore } from '../notification/notification.js'

/**
 * The band's conversation. Held oldest first, which is reading order, while the API answers newest
 * first so that page 1 is the newest page: every page is reversed on arrival.
 *
 * The pagination helpers are the ones the direct message thread already uses (#955). They key on
 * `@id` and `creation_datetime`, both of which a chat message carries, so nothing there needed
 * widening.
 */
export const useBandSpaceChatStore = defineStore('bandSpaceChat', () => {
  // Which space's conversation is held, which is how a live signal knows whether any of this is on
  // screen. Chat.vue clears the store when it unmounts and AppBandLayout keys <router-view> on the
  // space id, so leaving the tab or switching band empties it.
  const openBandSpaceId = ref(null)
  const messages = ref([])
  const totalMessages = ref(0)
  const isLoading = ref(false)
  const loadError = ref(null)
  const isLoadingOlder = ref(false)
  const loadOlderError = ref(null)
  const isSending = ref(false)
  const reactionError = ref(null)
  // Keyed `messageId:emoji`, so tapping the same pill twice before the first call answers is ignored
  // while tapping a different one is not.
  const pendingReactions = ref(new Set())

  const hasOlderMessages = computed(() =>
    hasOlderToLoad(messages.value.length, totalMessages.value)
  )

  /**
   * The total can only go up, because nothing deletes a message yet. A response that was issued
   * before a send lands with a total that predates it, and taking it at face value would hide the
   * « charger les messages plus anciens » button while history is still unread. Revisit at #967,
   * which is when a message can start disappearing.
   */
  function knownTotal(reported) {
    return Math.max(reported ?? 0, totalMessages.value, messages.value.length)
  }

  // Compared after every await: switching space remounts the view, and a slow first page must not
  // land on top of the space the member has already moved to.
  let loadToken = 0

  /**
   * Silent is a live signal refetching the newest page (#963): no spinner over a conversation that is
   * already readable, and a failure leaves what is on screen alone rather than replacing a working
   * pane with an error nobody asked for.
   *
   * It deliberately does **not** bump `loadToken`, it reads the current one the way
   * loadOlderMessages() does. Bumping it would cancel a « charger les messages plus anciens » that is
   * in flight, so a message arriving at the wrong moment would silently throw away the page of
   * history the member just asked for.
   */
  async function loadMessages(bandSpaceId, { silent = false } = {}) {
    const token = silent ? loadToken : ++loadToken
    if (!silent) {
      openBandSpaceId.value = bandSpaceId
      isLoading.value = true
      loadError.value = null
      loadOlderError.value = null
    }

    try {
      const response = await bandSpaceChatApi.getMessages(bandSpaceId)
      if (token !== loadToken) {
        return
      }
      // Merged, not replaced: the composer is usable while this is in flight, and a message sent
      // meanwhile would otherwise be wiped from the pane by a response that predates it. The store
      // is cleared when the view mounts, so on a normal load there is nothing to merge with.
      messages.value = mergeMessages(messages.value, (response.member || []).reverse())
      totalMessages.value = knownTotal(response.totalItems)
    } catch (e) {
      console.error('Failed to load the chat:', e)
      if (!silent && token === loadToken) {
        messages.value = []
        totalMessages.value = 0
        loadError.value = 'Impossible de charger la discussion'
      }
    } finally {
      if (!silent && token === loadToken) {
        isLoading.value = false
      }
    }
  }

  /**
   * Prepends the next page of history. The page to ask for is derived from how many messages are
   * held rather than from a counter, so it cannot drift.
   */
  async function loadOlderMessages(bandSpaceId) {
    if (isLoadingOlder.value || !hasOlderMessages.value) {
      return
    }

    const token = loadToken
    isLoadingOlder.value = true
    loadOlderError.value = null

    try {
      const response = await bandSpaceChatApi.getMessages(bandSpaceId, {
        page: nextOlderPageToLoad(messages.value.length)
      })
      if (token !== loadToken) {
        return
      }
      messages.value = mergeMessages(messages.value, (response.member || []).reverse())
      totalMessages.value = knownTotal(response.totalItems)
    } catch (e) {
      console.error('Failed to load older chat messages:', e)
      loadOlderError.value = 'Impossible de charger les messages plus anciens.'
    } finally {
      isLoadingOlder.value = false
    }
  }

  /**
   * Appends through the merge rather than pushing, so the message is de-duplicated by `@id`: the
   * sender's own signal can beat their own POST response back, because the server publishes inside the
   * send and answers afterwards, and the refetch it triggers has then already added it (#963).
   */
  async function sendMessage(bandSpaceId, content) {
    isSending.value = true

    try {
      const message = await bandSpaceChatApi.postMessage(bandSpaceId, content)
      const heldBefore = messages.value.length
      messages.value = mergeMessages(messages.value, [message])
      totalMessages.value += messages.value.length - heldBefore
    } finally {
      isSending.value = false
    }
  }

  /**
   * Adds or takes back the viewer's reaction, whichever way the pill is currently pointing (#968).
   *
   * Optimistic, because a reaction is a tap and a tap has to answer instantly. Everything is looked
   * up by message id rather than by index, including the rollback: a live signal can replace the
   * whole list while the request is in flight, and patching position 4 of a list that has since
   * grown at the top would rewrite the wrong message. For the same reason a failure is undone
   * against the row on screen rather than against a copy taken before the tap, see
   * rolledBackReactions().
   */
  async function toggleReaction(bandSpaceId, messageId, emojiKey) {
    const pendingKey = `${messageId}:${emojiKey}`
    const held = messages.value.find((message) => message.id === messageId)
    if (!held || pendingReactions.value.has(pendingKey)) {
      return
    }

    const wasReacted = hasReacted(held.reactions, emojiKey)

    pendingReactions.value = new Set(pendingReactions.value).add(pendingKey)
    reactionError.value = null
    patchReactions(messageId, toggledReactions(held.reactions, emojiKey))

    try {
      if (wasReacted) {
        await bandSpaceChatApi.removeReaction(bandSpaceId, messageId, emojiKey)

        return
      }
      // The add answers with the whole message, so the counts other members left meanwhile land here
      // too rather than waiting for the next refetch.
      const updated = await bandSpaceChatApi.addReaction(bandSpaceId, messageId, emojiKey)
      patchReactions(messageId, updated.reactions ?? [])
    } catch (e) {
      console.error('Failed to toggle a chat reaction:', e)
      // Both halves are gated on the message still being held: the member can move to another band
      // while the request is in flight, and an error banner about a tap made somewhere else would
      // then sit over a conversation it has nothing to do with.
      const live = messages.value.find((message) => message.id === messageId)
      if (live) {
        patchReactions(messageId, rolledBackReactions(live.reactions, emojiKey, wasReacted))
        // The server's own wording when the server answered, since every refusal on this endpoint
        // is already French. A transport failure has no status and would otherwise surface axios'
        // English one.
        reactionError.value = e.status
          ? e.message
          : 'Impossible de mettre à jour la réaction, veuillez réessayer.'
      }
    } finally {
      const stillPending = new Set(pendingReactions.value)
      stillPending.delete(pendingKey)
      pendingReactions.value = stillPending
    }
  }

  function patchReactions(messageId, reactions) {
    messages.value = messages.value.map((message) =>
      message.id === messageId ? { ...message, reactions } : message
    )
  }

  /**
   * Opening the tab is reading it. The badge lives on the notification payload rather than in this
   * store, because the sidebar shows it from every other module too, so clearing it means refreshing
   * that payload, the same shape the direct message store uses after marking a thread read.
   */
  async function markAsRead(bandSpaceId) {
    try {
      await bandSpaceChatApi.markAsRead(bandSpaceId)
      await useNotificationStore().loadNotifications()
    } catch (e) {
      console.error('Failed to mark the chat as read:', e)
    }
  }

  /**
   * A message landed in one of this member's channels (#963).
   *
   * The signal carries a band space id and nothing else, so what is on screen is refetched from the
   * API rather than patched from the payload: no message content travels over the hub. A null id is a
   * reconnect, which means the conversation on screen, whichever it is, is stale.
   *
   * Exactly one notification refresh per signal, whichever branch runs. markAsRead() refreshes the
   * badge on its way out, so refreshing it here too would race the count back to the value it had
   * before the read landed.
   */
  async function handleIncomingMessage(bandSpaceId) {
    const openId = openBandSpaceId.value
    if (!isForTheOpenConversation(bandSpaceId, openId)) {
      // None of this conversation is on screen, so the sidebar badge is the whole of the update.
      await useNotificationStore().loadNotifications()

      return
    }

    await loadMessages(openId, { silent: true })

    // Arriving while the tab is in front is reading it, the same rule the inbox uses. In a background
    // tab nobody has seen it, and marking it read would make the badge lie.
    //
    // Still on the same channel, checked again after the refetch: the member can leave it while that
    // is in flight, and `visibilityState` cannot tell "still reading this one" from "moved on in the
    // same visible tab". Marking it read then would clear the badge for a message nobody saw.
    if (openBandSpaceId.value === openId && globalThis.document?.visibilityState === 'visible') {
      await markAsRead(openId)

      return
    }
    await useNotificationStore().loadNotifications()
  }

  function clear() {
    // Bumped so nothing already in flight lands in a pane the member has left, a silent refresh
    // included. Chat.vue clears on unmount, which is the one moment a response has nowhere to go.
    loadToken += 1
    openBandSpaceId.value = null
    messages.value = []
    totalMessages.value = 0
    isLoading.value = false
    loadError.value = null
    isLoadingOlder.value = false
    loadOlderError.value = null
    isSending.value = false
    reactionError.value = null
    pendingReactions.value = new Set()
  }

  return {
    messages: readonly(messages),
    totalMessages: readonly(totalMessages),
    isLoading: readonly(isLoading),
    loadError: readonly(loadError),
    isLoadingOlder: readonly(isLoadingOlder),
    loadOlderError: readonly(loadOlderError),
    isSending: readonly(isSending),
    reactionError: readonly(reactionError),
    hasOlderMessages,
    loadMessages,
    loadOlderMessages,
    sendMessage,
    toggleReaction,
    markAsRead,
    handleIncomingMessage,
    clear
  }
})
