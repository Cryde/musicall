import { nextTick, onUnmounted, ref, watch } from 'vue'

/** Long enough to find the message with your eyes, short enough not to become part of the page. */
const HIGHLIGHT_DURATION_MS = 2500

/**
 * Scrolls to the message a store was asked to show and lights it up, in the band chat (#1039) and in a
 * conversation (#1380). By the time a request arrives the message is held, whether it was already on
 * the page or a window was loaded around it, so there is always something to land on. A frame after
 * the DOM update rather than on it, so the landing wins over anything else moving the pane in the
 * same tick.
 *
 * @param {() => {messageId: string}|null} focusRequest the store's, a fresh object per request
 * @param {(messageId: string) => string} elementIdOf the id of the element holding a message
 */
export function useMessageFocus(focusRequest, elementIdOf) {
  const highlightedMessageId = ref(null)
  let highlightTimer = null

  function focusMessage(messageId) {
    nextTick(() => {
      requestAnimationFrame(() => {
        const element = document.getElementById(elementIdOf(messageId))
        if (!element) {
          return
        }

        element.scrollIntoView({ block: 'center' })
        highlightedMessageId.value = messageId
        clearTimeout(highlightTimer)
        highlightTimer = setTimeout(() => {
          highlightedMessageId.value = null
        }, HIGHLIGHT_DURATION_MS)
      })
    })
  }

  watch(focusRequest, (request) => {
    if (request) {
      focusMessage(request.messageId)
    }
  })

  onUnmounted(() => clearTimeout(highlightTimer))

  return { highlightedMessageId, focusMessage }
}
