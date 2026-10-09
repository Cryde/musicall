<template>
  <span class="inline-flex items-baseline gap-2 tabular-nums">
    <span v-if="totals.income.total > 0" class="text-green-700 dark:text-green-400">
      <i class="pi pi-arrow-up text-xs" aria-hidden="true" />
      <span class="sr-only">Revenus :</span>
      {{ formatAmount(totals.income.total) }}
    </span>
    <span v-if="totals.expense.total > 0" class="text-red-700 dark:text-red-400">
      <i class="pi pi-arrow-down text-xs" aria-hidden="true" />
      <span class="sr-only">Dépenses :</span>
      {{ formatAmount(totals.expense.total) }}
    </span>
    <span v-if="totals.income.total === 0 && totals.expense.total === 0">{{ formatAmount(0) }}</span>
  </span>
</template>

<script setup>
import { formatAmount } from '../../../utils/currency.js'

/** A category's incomes and expenses side by side, never added together (#1169). */
defineProps({
  totals: { type: Object, required: true }
})
</script>
