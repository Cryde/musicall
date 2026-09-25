<template>
  <ul class="mt-2 first:mt-0 flex list-none flex-col gap-1 p-0 m-0">
    <li v-for="card in cards" :key="card.key">
      <RouterLink
        v-if="card.route"
        :to="card.route"
        :class="['no-underline!', CARD_CLASS, isMine ? MINE_CARD_CLASS : THEIR_CARD_CLASS]"
      >
        <i :class="['pi', card.icon, 'text-xs', 'shrink-0']" aria-hidden="true" />
        <span class="truncate">{{ card.label }}</span>
      </RouterLink>

      <!-- Deleted, so there is nothing left to open: the label is the snapshot taken when it was
           attached, which is what keeps the message saying what it pointed at. -->
      <span
        v-else
        :class="[CARD_CLASS, isMine ? MINE_CARD_CLASS : THEIR_CARD_CLASS, 'opacity-70']"
      >
        <i :class="['pi', card.icon, 'text-xs', 'shrink-0']" aria-hidden="true" />
        <span class="truncate">{{ card.label }}</span>
        <span v-if="!card.isAvailable" class="shrink-0 text-xs italic">(supprimé)</span>
      </span>
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { routeForResult, searchTypeFor } from '../../../utils/bandSpaceSearch.js'

const CARD_CLASS = 'flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-sm max-w-full'
const THEIR_CARD_CLASS =
  'border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-800 text-surface-800 dark:text-surface-100'
const MINE_CARD_CLASS = 'border-white/40 bg-white/15 text-white'
const FALLBACK_ICON = 'pi-link'

const props = defineProps({
  /** The `attachments` of one ChatMessage, straight from the API. */
  attachments: { type: Array, required: true },
  bandSpaceId: { type: String, required: true },
  /** The sender's own bubble is dark, so a card inside it needs the light palette. */
  isMine: { type: Boolean, default: false }
})

/**
 * The deep link comes from the same mapping the command palette uses: those paths belong to the Vue
 * router, which is why the API sends a kind and an id rather than a ready made url. A kind the router
 * does not know, and a target that has been deleted, both render as plain text.
 */
const cards = computed(() =>
  props.attachments.map((attachment) => ({
    key: `${attachment.type}-${attachment.target_id}`,
    label: attachment.label,
    icon: searchTypeFor(attachment.type)?.icon ?? FALLBACK_ICON,
    isAvailable: attachment.is_available,
    route: attachment.is_available
      ? routeForResult(
          { type: attachment.type, resource_id: attachment.target_id },
          props.bandSpaceId
        )
      : null
  }))
)
</script>
