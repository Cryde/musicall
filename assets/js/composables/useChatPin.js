import { useToast } from 'primevue/usetoast'
import { useBandSpaceChatStore } from '../store/bandSpace/bandSpaceChat.js'

/**
 * Pinning a chat message, shared by the message list and the pinned bar (#969).
 *
 * Both offer the same action on the same message, so the wording and the failure handling live here
 * rather than twice: the ten pin cap and a space pending deletion both answer 409 with a French
 * sentence meant to be shown as it is.
 *
 * @param {string} bandSpaceId
 */
export function useChatPin(bandSpaceId) {
  const chatStore = useBandSpaceChatStore()
  const toast = useToast()

  function isPending(message) {
    return chatStore.pendingPinMessageId === message.id
  }

  function actionLabel(message) {
    return message.is_pinned ? 'Détacher ce message' : 'Épingler ce message'
  }

  async function togglePin(message) {
    try {
      if (message.is_pinned) {
        await chatStore.unpinMessage(bandSpaceId, message.id)

        return
      }
      await chatStore.pinMessage(bandSpaceId, message.id)
    } catch (e) {
      toast.add({ severity: 'error', summary: e.message, life: 5000 })
    }
  }

  return { isPending, actionLabel, togglePin }
}
