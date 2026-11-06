import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useMessageStore } from '../store/message/message.js'

/**
 * Sends a first message to a member and opens the conversation: the free message dialog and the trial
 * lesson request (#1311) share it, so they fail and land the same way.
 */
export function useSendMessage() {
  const { t } = useI18n()
  const router = useRouter()
  const messageStore = useMessageStore()

  const isSending = ref(false)
  const errorMessage = ref('')

  /** @returns {Promise<boolean>} whether the message went out */
  async function send({ recipientId, content, contactOrigin = null }) {
    errorMessage.value = ''
    isSending.value = true
    try {
      const threadId = await messageStore.postMessage({ recipientId, content, contactOrigin })
      trackUmamiEvent('message-send')
      if (threadId) {
        router.push({ name: 'app_messages', params: { threadId } })
      }
      return true
    } catch (e) {
      if (e.status === 429 || e.originalError?.response?.status === 429) {
        errorMessage.value = t('message.error.too_many_messages')
      } else {
        errorMessage.value = e.message || t('message.send_modal.error')
      }
      return false
    } finally {
      isSending.value = false
    }
  }

  function resetError() {
    errorMessage.value = ''
  }

  return { isSending, errorMessage, send, resetError }
}
