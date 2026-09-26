import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceChatApi from '../../api/bandSpace/band-space-chat.js'
import {
  hasReacted,
  rolledBackReactions,
  toggledReactions
} from '../../utils/chatReactionToggle.js'
import { isHeld, mayMergeNewestPage, paneAfterWindow } from '../../utils/chatWindow.js'
import { createCoalescedCall } from '../../utils/coalescedCall.js'
import {
  hasOlderToLoad,
  mergeMessages,
  nextOlderPageToLoad
} from '../../utils/messagePagination.js'
import { isForTheOpenConversation } from '../../utils/messageSignal.js'
import { useNotificationStore } from '../notification/notification.js'

/**
 * How long read signals are gathered before « Vu par » is refetched (#977). One message makes every
 * member watching refetch and then mark read, so their reads land spread over one or two round trips,
 * well under a second on a decent connection. A second and a half turns that burst into one page
 * load while the line still moves within two seconds of somebody reading.
 */
const READ_RECEIPT_COALESCE_MS = 1500

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
  // The « infos importantes » bar (#969). Its own list rather than a filter over `messages`, because
  // a pinned message is usually far up the history and therefore not on the page the pane holds.
  const pinnedMessages = ref([])
  // Which message has a pin call in flight, so its button can be disabled: a second click on
  // « Détacher » would otherwise come back 404 and toast something the member cannot act on.
  const pendingPinMessageId = ref(null)
  // Same idea for « créer une tâche » (#979), where a double click would create two tasks.
  const pendingTaskMessageId = ref(null)

  // Whether `messages` runs back from the newest message with no gap (#1039). It does, except after a
  // jump to a message far up the history, when the pane holds a window in the middle of it instead:
  // the page arithmetic then means nothing, reading on goes through the window endpoint in both
  // directions, and a live signal must not splice today's page into last March.
  const isAtTail = ref(true)
  const windowHasOlder = ref(false)
  const windowHasNewer = ref(false)
  const isLoadingNewer = ref(false)
  const loadNewerError = ref(null)
  const jumpError = ref(null)
  // The message a jump is fetching a window for, so a double click on « Aller au message » is one
  // request, and its button can say it is busy.
  const jumpingTo = ref(null)
  // What the list should scroll to and light up. A fresh object each time, so asking for the same
  // message twice still moves the pane.
  const focusRequest = ref(null)

  const hasOlderMessages = computed(() =>
    isAtTail.value
      ? hasOlderToLoad(messages.value.length, totalMessages.value)
      : windowHasOlder.value
  )
  const hasNewerMessages = computed(() => !isAtTail.value && windowHasNewer.value)

  /**
   * The total can only go up. A response that was issued before a send lands with a total that
   * predates it, and taking it at face value would hide the « charger les messages plus anciens »
   * button while history is still unread.
   *
   * #967 does not change that: deleting a message leaves a tombstone in the list and in the count,
   * so the total still never falls.
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
      // A silent refetch is page 1, which only belongs in a pane that runs back from the newest
      // message: one that left for a window meanwhile must not have today spliced into it (#1039).
      if (token !== loadToken || !mayMergeNewestPage({ silent, isAtTail: isAtTail.value })) {
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
      if (!isAtTail.value) {
        const page = await bandSpaceChatApi.getMessageWindow(bandSpaceId, {
          before: messages.value[0]?.id
        })
        if (token !== loadToken) {
          return
        }
        messages.value = mergeMessages(messages.value, page.messages ?? [])
        windowHasOlder.value = page.has_older

        return
      }

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
   * Reads on towards the present from a window (#1039). Reaching the newest message ends the window:
   * the pane is a contiguous tail again, so the page arithmetic, the live refetch and marking read all
   * apply once more.
   */
  async function loadNewerMessages(bandSpaceId) {
    if (isLoadingNewer.value || !hasNewerMessages.value) {
      return
    }

    const token = loadToken
    isLoadingNewer.value = true
    loadNewerError.value = null

    try {
      const page = await bandSpaceChatApi.getMessageWindow(bandSpaceId, {
        after: messages.value.at(-1)?.id
      })
      if (token !== loadToken) {
        return
      }
      messages.value = mergeMessages(messages.value, page.messages ?? [])
      applyWindow(page)
      if (isAtTail.value) {
        await markAsRead(bandSpaceId)
      }
    } catch (e) {
      console.error('Failed to load newer chat messages:', e)
      loadNewerError.value = 'Impossible de charger les messages plus récents.'
    } finally {
      isLoadingNewer.value = false
    }
  }

  /** After a window answer has been merged in: see paneAfterWindow(). */
  function applyWindow(page) {
    const pane = paneAfterWindow(page, messages.value.length)
    isAtTail.value = pane.isAtTail
    windowHasOlder.value = pane.hasOlder
    windowHasNewer.value = pane.hasNewer
    if (pane.total !== null) {
      totalMessages.value = pane.total
    }
  }

  /**
   * Shows one message in context, wherever it is (#1039): a pinned one, the one a mention points at,
   * a link to it. Already held, the pane just scrolls to it; otherwise the pane is replaced by a
   * window around it, which is what saves a pin from a year ago dozens of page loads.
   */
  async function jumpToMessage(bandSpaceId, messageId) {
    jumpError.value = null
    if (isHeld(messages.value, messageId)) {
      focusRequest.value = { messageId }

      return
    }

    if (jumpingTo.value === messageId) {
      return
    }

    const token = ++loadToken
    // Out of the live end before the request rather than after it: a message landing while this is in
    // flight must take the window branch of handleIncomingMessage(), not splice today's page into the
    // pane the window is about to replace. Put back if the jump fails.
    const wasAtTail = isAtTail.value
    isAtTail.value = false
    jumpingTo.value = messageId
    try {
      const page = await bandSpaceChatApi.getMessageWindow(bandSpaceId, { around: messageId })
      if (token !== loadToken) {
        return
      }
      messages.value = page.messages ?? []
      loadOlderError.value = null
      loadNewerError.value = null
      applyWindow(page)
      focusRequest.value = { messageId }
    } catch (e) {
      console.error('Failed to jump to a chat message:', e)
      if (token === loadToken) {
        isAtTail.value = wasAtTail
        jumpError.value =
          e.status === 404
            ? "Ce message n'est plus disponible."
            : "Impossible d'afficher ce message, veuillez réessayer."
      }
    } finally {
      if (jumpingTo.value === messageId) {
        jumpingTo.value = null
      }
    }
  }

  function dismissJumpError() {
    jumpError.value = null
  }

  /** Back to the live end of the conversation from a window, as it is when the tab opens. */
  async function returnToLatest(bandSpaceId) {
    loadToken += 1
    messages.value = []
    applyWindow({ has_older: false, has_newer: false, total_items: 0 })
    await loadMessages(bandSpaceId)
    if (!loadError.value) {
      await markAsRead(bandSpaceId)
    }
  }

  /**
   * Appends through the merge rather than pushing, so the message is de-duplicated by `@id`: the
   * sender's own signal can beat their own POST response back, because the server publishes inside the
   * send and answers afterwards, and the refetch it triggers has then already added it (#963).
   */
  async function sendMessage(bandSpaceId, content, attachments = [], media = null) {
    isSending.value = true

    try {
      const message = await bandSpaceChatApi.postMessage(bandSpaceId, content, attachments, media)
      // Written from a window far up the history: appending it there would open a gap between the
      // window and today. The member wrote it, so they mean to see it, where it really is.
      if (!isAtTail.value) {
        await returnToLatest(bandSpaceId)

        return
      }
      const heldBefore = messages.value.length
      messages.value = mergeMessages(messages.value, [message])
      totalMessages.value += messages.value.length - heldBefore
    } finally {
      isSending.value = false
    }
  }

  async function loadPinnedMessages(bandSpaceId) {
    // Checked against the space on screen, not against the load token: switching band while this is in
    // flight must not hang another band's pins over the conversation now open, but the token moves on
    // every load and every jump (#1039), and Chat.vue starts the list load right after this one, which
    // used to throw every first answer away and leave the bar hidden.
    try {
      const pinned = await bandSpaceChatApi.getPinnedMessages(bandSpaceId)
      if (openBandSpaceId.value === bandSpaceId) {
        pinnedMessages.value = pinned
      }
    } catch (e) {
      // The conversation itself is what the member came for, so a bar that cannot load stays hidden
      // rather than turning the pane into an error.
      console.error('Failed to load the pinned messages:', e)
    }
  }

  /**
   * Pin, then unpin. Both re-read the bar rather than splicing the answer into it: the server orders
   * by pin time and settles what a second member pinning the same message meant, so re-reading is the
   * only version that cannot disagree with it.
   *
   * Errors are rethrown, unlike the load above: the cap and the deletion grace period both answer 409
   * with a sentence the member needs to see.
   */
  async function pinMessage(bandSpaceId, messageId) {
    await runPinCall(bandSpaceId, messageId, () =>
      bandSpaceChatApi.pinMessage(bandSpaceId, messageId)
    )
  }

  async function unpinMessage(bandSpaceId, messageId) {
    await runPinCall(bandSpaceId, messageId, () =>
      bandSpaceChatApi.unpinMessage(bandSpaceId, messageId)
    )
  }

  async function runPinCall(bandSpaceId, messageId, call) {
    pendingPinMessageId.value = messageId
    try {
      replaceHeldMessage(await call())
      await loadPinnedMessages(bandSpaceId)
    } finally {
      pendingPinMessageId.value = null
    }
  }

  /**
   * Refreshes a message already on screen, and only that: merging would *insert* one that is not,
   * which after unpinning something old would drop a message from last month into the pane as though
   * history had been loaded.
   */
  function replaceHeldMessage(updated) {
    const iri = updated?.['@id']
    if (!iri) {
      return
    }
    messages.value = messages.value.map((message) => (message['@id'] === iri ? updated : message))
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
   * Rewrites one message the member already sent (#966).
   *
   * Merged rather than spliced: the helper matches on `@id` and keeps the position, so the copy the
   * server answers with lands exactly where the conversation already holds it, with its re-rendered
   * content and its edit date. The count is untouched, because an edit adds nothing.
   *
   * No shared saving flag: an edit is per message and the row that opened it owns its own, the way
   * the task comment thread does. Errors are left to it too, since the box stays open on a failure
   * rather than losing what was typed.
   */
  async function editMessage(bandSpaceId, messageId, content) {
    const message = await bandSpaceChatApi.updateMessage(bandSpaceId, messageId, content)
    messages.value = mergeMessages(messages.value, [message])
  }

  /**
   * Turns the message into a tombstone in place rather than refetching the page (#967).
   *
   * The server answers 204 and keeps the row, so the only thing that changes is these two fields.
   * Dropping it from the list instead would take the message out from under everyone else's scroll
   * position until their next refetch put it back.
   */
  async function deleteMessage(bandSpaceId, messageId) {
    await bandSpaceChatApi.deleteMessage(bandSpaceId, messageId)
    messages.value = messages.value.map((message) =>
      message.id === messageId ? { ...message, content: '', is_deleted: true } : message
    )
  }

  /**
   * Turns a message into a task (#979).
   *
   * The card is added to the message here rather than by refetching the page: the server wrote
   * exactly this row, label included, because an attachment snapshots its target's title and the
   * title is the one the task was just given. Errors are rethrown, like the pin ones: the cap and
   * the deletion grace period both answer 409 with a sentence the member needs to read.
   */
  async function createTaskFromMessage(bandSpaceId, messageId) {
    pendingTaskMessageId.value = messageId
    try {
      const task = await bandSpaceChatApi.createTaskFromMessage(bandSpaceId, messageId)
      const card = { type: 'task', target_id: task.id, label: task.title, is_available: true }
      messages.value = messages.value.map((message) =>
        message.id === messageId
          ? { ...message, attachments: [...(message.attachments ?? []), card] }
          : message
      )

      return task
    } finally {
      pendingTaskMessageId.value = null
    }
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

    // Reading a window far up the history: splicing today's page into it would break the pane, and the
    // member has not seen the new message, so it waits behind « Revenir aux messages récents » and the
    // badge counts it.
    if (!isAtTail.value) {
      windowHasNewer.value = true
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

  const readReceiptRefresh = createCoalescedCall({
    delayMs: READ_RECEIPT_COALESCE_MS,
    call: () => {
      // The « Vu par » line sits under the newest message, which a window up the history does not hold.
      if (openBandSpaceId.value && isAtTail.value) {
        loadMessages(openBandSpaceId.value, { silent: true })
      }
    }
  })

  /**
   * Another member read one of this member's channels (#977), so the « Vu par » on screen may be
   * stale.
   *
   * Only the newest page is refetched, because the line is drawn under the last message and the last
   * message is always on it. And nothing else happens: no markAsRead(), which would publish a read of
   * our own and feed the cascade this exists to bound, and no badge refresh, because somebody else
   * reading changes nothing that is unread for us.
   */
  function handleChatRead(bandSpaceId) {
    if (bandSpaceId && bandSpaceId === openBandSpaceId.value) {
      readReceiptRefresh.request()
    }
  }

  function clear() {
    // A refetch gathered for the pane being left would otherwise land in whatever is opened next.
    readReceiptRefresh.cancel()
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
    pinnedMessages.value = []
    pendingPinMessageId.value = null
    pendingTaskMessageId.value = null
    isAtTail.value = true
    windowHasOlder.value = false
    windowHasNewer.value = false
    isLoadingNewer.value = false
    loadNewerError.value = null
    jumpError.value = null
    jumpingTo.value = null
    focusRequest.value = null
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
    pinnedMessages: readonly(pinnedMessages),
    pendingPinMessageId: readonly(pendingPinMessageId),
    pendingTaskMessageId: readonly(pendingTaskMessageId),
    isAtTail: readonly(isAtTail),
    isLoadingNewer: readonly(isLoadingNewer),
    loadNewerError: readonly(loadNewerError),
    jumpError: readonly(jumpError),
    jumpingTo: readonly(jumpingTo),
    focusRequest: readonly(focusRequest),
    hasOlderMessages,
    hasNewerMessages,
    loadMessages,
    loadOlderMessages,
    loadNewerMessages,
    jumpToMessage,
    dismissJumpError,
    returnToLatest,
    loadPinnedMessages,
    pinMessage,
    unpinMessage,
    sendMessage,
    toggleReaction,
    editMessage,
    deleteMessage,
    createTaskFromMessage,
    markAsRead,
    handleIncomingMessage,
    handleChatRead,
    clear
  }
})
