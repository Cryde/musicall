<template>
  <!-- A button, since a click goes to the answered message, even once deleted: the quote still
       says who was answered and where. -->
  <button
    type="button"
    class="mb-1 block w-full min-w-0 rounded-lg border-l-4 px-2 py-1 text-left text-xs transition-colors"
    :class="
      isMine
        ? 'border-white/70 bg-white/15 hover:bg-white/25'
        : 'border-primary-500 bg-surface-200 hover:bg-surface-300 dark:bg-surface-600 dark:hover:bg-surface-500'
    "
    :aria-label="t('band_space_chat.reply.open_quoted', { name: quote.author_display_name })"
    @click="emit('open')"
  >
    <span class="block truncate font-semibold">{{ quote.author_display_name }}</span>
    <span class="block truncate" :class="{ italic: !quote.excerpt || quote.is_deleted }">
      {{ replyQuoteText(quote, t) }}
    </span>
  </button>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { replyQuoteText } from '../../../utils/chatReply.js'

/** The message a reply answers, at the top of its bubble (#1377). */
defineProps({
  /** The message's `reply_to`. */
  quote: { type: Object, required: true },
  isMine: { type: Boolean, default: false }
})

const emit = defineEmits(['open'])

const { t } = useI18n()
</script>
