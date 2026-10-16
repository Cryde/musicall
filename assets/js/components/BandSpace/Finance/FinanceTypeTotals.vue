<template>
  <span class="inline-flex items-baseline gap-2 tabular-nums">
    <span v-if="totals.income.total > 0" class="whitespace-nowrap text-green-700 dark:text-green-400">
      <i class="pi pi-arrow-up text-xs" aria-hidden="true" />
      <span class="sr-only">{{ t('band_space_finance.type_totals.income') }}</span>
      {{ formatAmount(totals.income.total) }}
    </span>
    <span v-if="totals.expense.total > 0" class="whitespace-nowrap text-red-700 dark:text-red-400">
      <i class="pi pi-arrow-down text-xs" aria-hidden="true" />
      <span class="sr-only">{{ t('band_space_finance.type_totals.expense') }}</span>
      {{ formatAmount(totals.expense.total) }}
    </span>
    <span v-if="totals.income.total === 0 && totals.expense.total === 0">{{ formatAmount(0) }}</span>
  </span>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { formatAmount } from '../../../utils/currency.js'

const { t } = useI18n()

/** A category's incomes and expenses side by side, never added together (#1169). */
defineProps({
  totals: { type: Object, required: true }
})
</script>
