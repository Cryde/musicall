import { useMediaQuery } from '@vueuse/core'
import { computed, ref, watch } from 'vue'

/**
 * The sheet a long press opens on a message where there is no hover to reveal its actions, shared by a
 * direct conversation and the band chat. The open message is read back from the list by id, so the
 * sheet follows it when it changes while open (a reaction, an edit, a pin).
 *
 * @param {() => object[]} messages the conversation's messages
 */
export function useMessageActionsSheet(messages) {
  const actionsInSheet = useMediaQuery('(hover: none)')
  const actionsMessageId = ref(null)
  const isActionsOpen = ref(false)
  const actionsMessage = computed(
    () => messages().find((message) => message.id === actionsMessageId.value) ?? null
  )

  function openActions(message) {
    actionsMessageId.value = message.id
    isActionsOpen.value = true
  }

  // A message that leaves the list while its sheet is open (a refetch, another conversation) takes the
  // sheet with it, rather than leaving actions that would point at nothing.
  watch(actionsMessage, (message) => {
    if (!message) isActionsOpen.value = false
  })

  return { actionsInSheet, isActionsOpen, actionsMessage, openActions }
}
