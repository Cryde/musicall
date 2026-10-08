<template>
  <!-- One line, for the inbox row and the conversation header. -->
  <p
    v-if="compact"
    class="flex items-center gap-1.5 min-w-0 text-xs text-surface-600 dark:text-surface-300"
  >
    <i :class="contactOriginIcon(origin)" class="text-[0.7rem] shrink-0" aria-hidden="true" />
    <span class="truncate">
      {{ contactOriginLabel(origin) }}<template v-if="details"> · {{ details }}</template>
    </span>
  </p>

  <!-- Above the message it was sent with: the reason this message, and maybe this conversation, exists. -->
  <div
    v-else
    class="flex items-start gap-2 rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 px-3 py-2 text-xs text-surface-700 dark:text-surface-200"
  >
    <i :class="contactOriginIcon(origin)" class="mt-0.5 shrink-0" aria-hidden="true" />
    <div class="min-w-0">
      <p class="font-medium">{{ contactOriginLabel(origin) }}</p>
      <p v-if="details" class="text-surface-600 dark:text-surface-300">{{ details }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  contactOriginDetails,
  contactOriginIcon,
  contactOriginLabel
} from '../../utils/contactOrigin.js'

const props = defineProps({
  // The API's contact_origin or latest_contact_origin.
  origin: { type: Object, required: true },
  compact: { type: Boolean, default: false }
})

const details = computed(() => contactOriginDetails(props.origin))
</script>
