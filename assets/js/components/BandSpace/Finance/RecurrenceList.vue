<template>
  <div>
    <div class="flex items-center justify-between mb-3 max-md:mx-1 max-md:mb-2">
      <h3 class="text-sm font-semibold text-surface-600 dark:text-surface-300 uppercase tracking-wide max-md:text-[0.9375rem] max-md:normal-case max-md:tracking-normal max-md:text-surface-900 max-md:dark:text-surface-0">{{ t('band_space_finance.recurrence_list.title') }}</h3>
      <button
        class="text-sm text-primary hover:underline max-md:font-semibold max-md:text-primary-700 max-md:dark:text-primary-300"
        @click="emit('add')"
      >
        {{ t('band_space_finance.recurrence_list.add') }}
      </button>
    </div>

    <!-- On a phone the list sits in a box, as the blocks above it do. -->
    <div
      class="max-md:overflow-hidden max-md:rounded-2xl max-md:border max-md:border-surface-200 max-md:bg-surface-0 max-md:divide-y max-md:divide-surface-200 dark:max-md:border-surface-700 dark:max-md:bg-surface-900 dark:max-md:divide-surface-700"
    >
      <div v-if="recurrences.length === 0" class="text-sm text-surface-400 italic py-1 max-md:px-4 max-md:py-3">
        {{ t('band_space_finance.recurrence_list.empty') }}
      </div>

      <div
        v-for="recurrence in recurrences"
        :key="recurrence.id"
        class="py-2 px-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer max-md:rounded-none max-md:px-4 max-md:py-3"
        @click="emit('edit', recurrence)"
      >
        <div class="flex items-center gap-2">
          <span class="flex-1 text-sm truncate min-w-0 font-medium">{{ recurrence.label }}</span>
          <span
            class="text-xs font-medium px-1.5 py-0.5 rounded-full flex-shrink-0"
            :class="recurrence.is_active
              ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
              : 'bg-surface-200 text-surface-500 dark:bg-surface-600 dark:text-surface-400'"
          >
            {{ recurrence.is_active ? t('band_space_finance.recurrence_list.active') : t('band_space_finance.recurrence_list.inactive') }}
          </span>
        </div>
        <div class="flex items-center gap-2 mt-1 text-xs text-surface-500">
          <span class="font-medium px-1.5 py-0.5 rounded-full bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-300">
            {{ intervalLabel(recurrence.interval) }}
          </span>
          <span class="font-medium text-sm text-surface-700 dark:text-surface-200">{{ formatRecurrenceAmount(recurrence) }}</span>
          <span>{{ formatDateCompactWithYear(recurrence.start_date) }} {{ rangeDash() }} {{ formatDateCompactWithYear(recurrence.end_date) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { rangeDash } from '../../../i18n/formatLocale.js'
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'

const { t } = useI18n()

defineProps({
  recurrences: { type: Array, required: true }
})

const emit = defineEmits(['edit', 'add'])

const INTERVAL_LABEL_KEYS = {
  weekly: 'band_space_finance.recurrence_list.interval.weekly',
  monthly: 'band_space_finance.recurrence_list.interval.monthly',
  quarterly: 'band_space_finance.recurrence_list.interval.quarterly',
  yearly: 'band_space_finance.recurrence_list.interval.yearly'
}

function intervalLabel(interval) {
  const key = INTERVAL_LABEL_KEYS[interval]
  return key ? t(key) : interval
}

function formatRecurrenceAmount(recurrence) {
  return formatAmount(recurrence.amount)
}
</script>
