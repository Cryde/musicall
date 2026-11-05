<template>
  <component
    :is="canOpen ? 'button' : 'div'"
    :type="canOpen ? 'button' : undefined"
    class="flex w-full items-center gap-3 px-2 py-2.5 text-left"
    :class="{ 'transition-colors hover:bg-surface-100 dark:hover:bg-surface-700': canOpen }"
    @click="canOpen && emit('open', entry)"
  >
    <FinanceStatusDot :status="entry.status" />
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
      <span class="line-clamp-2 text-sm [overflow-wrap:anywhere]">{{ entry.label }}</span>
      <span class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-surface-600 dark:text-surface-300">
        <i
          v-if="entry.recurrence_id"
          class="pi pi-sync text-[0.625rem]"
          role="img"
          :aria-label="t('band_space_finance.common.recurrence')"
        />
        <span>{{ meta }}</span>
        <span
          v-if="entry.scope === 'personal'"
          class="rounded-full bg-purple-100 px-1.5 py-0.5 font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
        >
          {{ t('band_space_finance.common.personal_badge') }}
        </span>
      </span>
    </span>
    <FinanceSplitWarning v-if="entry.split_warning" />
    <span
      class="shrink-0 text-right text-sm font-semibold tabular-nums"
      :class="entry.type === 'expense' ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'"
    >
      {{ formatSignedEntryAmount(entry) }}
    </span>
    <i v-if="canOpen" class="pi pi-chevron-right shrink-0 text-xs text-surface-500" aria-hidden="true" />
  </component>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { formatSignedEntryAmount } from '../../../utils/financeEntryAmount.js'
import FinanceSplitWarning from './FinanceSplitWarning.vue'
import FinanceStatusDot from './FinanceStatusDot.vue'

/**
 * An entry on a phone: its status, its label on up to two lines, what it is under it, and an amount
 * signed by its type, since the type badge has no room. The whole row opens it.
 */
defineProps({
  entry: { type: Object, required: true },
  // « 30 nov. · Dépense » in a category, « Live & Concerts · Dépense » in the timeline
  meta: { type: String, required: true },
  // Someone else's personal entry is listed but cannot be opened, as on desktop.
  canOpen: { type: Boolean, default: true }
})

const emit = defineEmits(['open'])
const { t } = useI18n()
</script>
