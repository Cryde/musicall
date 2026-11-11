<template>
  <div class="mb-2 flex items-center gap-2" role="status">
    <div class="min-w-0 flex-1 border-l-4 border-primary-500 pl-2 text-sm">
      <p class="m-0 truncate font-semibold text-surface-700 dark:text-surface-200">
        {{ t('band_space_chat.reply.banner', { name: quote.author_display_name }) }}
      </p>
      <p
        class="m-0 truncate text-surface-600 dark:text-surface-300"
        :class="{ italic: !quote.excerpt }"
      >
        {{ replyQuoteText(quote, t) }}
      </p>
    </div>
    <Button
      icon="pi pi-times"
      severity="secondary"
      text
      rounded
      size="small"
      :aria-label="t('band_space_chat.reply.cancel')"
      @click="emit('cancel')"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { replyQuoteText } from '../../../utils/chatReply.js'

/** What the composer is answering (#1377), above it until the reply is sent or dropped. */
defineProps({
  /** The quote the reply will carry, see replyQuoteFromMessage(). */
  quote: { type: Object, required: true }
})

const emit = defineEmits(['cancel'])

const { t } = useI18n()
</script>
