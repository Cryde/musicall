<template>
  <section
    v-if="pinnedMessages.length > 0"
    class="shrink-0 border-b border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-800"
  >
    <button
      type="button"
      class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-700"
      :aria-expanded="isExpanded"
      aria-controls="chat-pinned-messages"
      :aria-label="toggleLabel"
      @click="toggle"
    >
      <i class="pi pi-bookmark-fill text-primary-700 dark:text-primary-300" aria-hidden="true" />
      <span class="font-medium">{{ headline }}</span>
      <i
        class="pi ml-auto text-xs"
        :class="isExpanded ? 'pi-chevron-up' : 'pi-chevron-down'"
        aria-hidden="true"
      />
    </button>

    <ul v-show="isExpanded" id="chat-pinned-messages" class="space-y-2 px-4 pb-3">
      <li v-for="message in pinnedMessages" :key="message['@id']" class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
          <!-- `truncate` keeps the excerpt to one line, and the sanitizer's `<br>` is neutralised so a
               multi line message cannot push the bar open. -->
          <p
            class="truncate text-sm text-surface-900 [&_.chat-mention]:font-semibold [&_a]:text-primary-700 [&_a]:underline dark:text-surface-0 dark:[&_a]:text-primary-300 [&_br]:hidden"
            v-html="autoLink(message.content)"
          />
          <p class="mt-0.5 text-xs text-surface-600 dark:text-surface-300">
            {{ message.author_username }}, épinglé par {{ message.pinned_by_username }}
          </p>
        </div>

        <button
          type="button"
          class="shrink-0 rounded-full px-2 py-1 text-surface-600 hover:bg-surface-200 disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-700"
          :disabled="chatPin.isPending(message)"
          :aria-label="`Détacher le message de ${message.author_username}`"
          @click="chatPin.togglePin(message)"
        >
          <i class="pi pi-times text-xs" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useChatPin } from '../../../composables/useChatPin.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { autoLink } from '../../../utils/autoLink.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const chatStore = useBandSpaceChatStore()
const chatPin = useChatPin(props.bandSpaceId)

const pinnedMessages = computed(() => chatStore.pinnedMessages)

// Null until the member says otherwise, so the bar opens itself for a single pin and stays out of the
// way once the band has several. Their choice wins from then on, including across a refetch.
const manualExpansion = ref(null)
const isExpanded = computed(() => manualExpansion.value ?? pinnedMessages.value.length <= 1)

const headline = computed(() =>
  pinnedMessages.value.length === 1
    ? '1 message épinglé'
    : `${pinnedMessages.value.length} messages épinglés`
)

// Built from the visible text rather than beside it: a speech input user says what they read, and a
// name that reworded the label would leave them unable to reach the control (WCAG 2.5.3).
const toggleLabel = computed(() =>
  isExpanded.value ? `${headline.value}, masquer` : `${headline.value}, afficher`
)

function toggle() {
  manualExpansion.value = !isExpanded.value
}
</script>
