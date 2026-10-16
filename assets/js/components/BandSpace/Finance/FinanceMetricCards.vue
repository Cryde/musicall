<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        {{ t('band_space_finance.totals.income') }}
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="t('band_space_finance.metric_cards.income_hint')"></i>
      </p>
      <p class="text-xl font-semibold text-green-700 dark:text-green-400">{{ formatAmount(summary?.total_income) }}</p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        {{ t('band_space_finance.totals.expense') }}
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="t('band_space_finance.metric_cards.expense_hint')"></i>
      </p>
      <p class="text-xl font-semibold text-red-600 dark:text-red-400">{{ formatAmount(summary?.total_expense) }}</p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        {{ t('band_space_finance.status.committed') }}
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="t('band_space_finance.metric_cards.committed_hint')"></i>
      </p>
      <p class="text-xl font-semibold text-orange-600 dark:text-orange-400">
        <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_committed) }}
      </p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        {{ t('band_space_finance.status.planned') }}
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="t('band_space_finance.metric_cards.planned_hint')"></i>
      </p>
      <p class="text-xl font-semibold text-blue-600 dark:text-blue-400">
        <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_planned) }}
      </p>
    </div>

    <div v-if="summary?.total_personal > 0" class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        {{ t('band_space_finance.metric_cards.personal') }}
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="t('band_space_finance.metric_cards.personal_hint')"></i>
      </p>
      <!-- Incomes and expenses apart, never added up (#1168) -->
      <FinanceTypeTotals :totals="personalTotals" class="text-xl font-semibold flex-wrap" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatAmount } from '../../../utils/currency.js'
import FinanceTypeTotals from './FinanceTypeTotals.vue'

const { t } = useI18n()

const props = defineProps({
  summary: { type: Object, default: null }
})

// The shape FinanceTypeTotals reads; only the totals are shown here.
const personalTotals = computed(() => ({
  income: { total: props.summary?.total_personal_income ?? 0 },
  expense: { total: props.summary?.total_personal_expense ?? 0 }
}))
</script>
