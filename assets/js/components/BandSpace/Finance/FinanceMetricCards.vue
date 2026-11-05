<template>
  <div>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 md:gap-4">
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl px-3 py-2.5 md:p-4 border max-md:dark:bg-surface-900 border-surface-200 dark:border-surface-700">
        <p class="text-sm max-md:text-[0.8125rem] text-surface-500 dark:text-surface-400 md:mb-1">
          {{ t('band_space_finance.totals.income') }}
          <i class="pi pi-info-circle text-xs ml-1 cursor-help max-md:hidden!" v-tooltip.top="t('band_space_finance.metric_cards.income_hint')"></i>
        </p>
        <p class="text-lg leading-snug md:text-xl font-semibold text-green-700 dark:text-green-400">{{ formatAmount(summary?.total_income) }}</p>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl px-3 py-2.5 md:p-4 border max-md:dark:bg-surface-900 border-surface-200 dark:border-surface-700">
        <p class="text-sm max-md:text-[0.8125rem] text-surface-500 dark:text-surface-400 md:mb-1">
          {{ t('band_space_finance.totals.expense') }}
          <i class="pi pi-info-circle text-xs ml-1 cursor-help max-md:hidden!" v-tooltip.top="t('band_space_finance.metric_cards.expense_hint')"></i>
        </p>
        <p class="text-lg leading-snug md:text-xl font-semibold text-red-600 dark:text-red-400">{{ formatAmount(summary?.total_expense) }}</p>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl px-3 py-2.5 md:p-4 border max-md:dark:bg-surface-900 border-surface-200 dark:border-surface-700">
        <p class="text-sm max-md:text-[0.8125rem] text-surface-500 dark:text-surface-400 md:mb-1">
          {{ t('band_space_finance.status.committed') }}
          <i class="pi pi-info-circle text-xs ml-1 cursor-help max-md:hidden!" v-tooltip.top="t('band_space_finance.metric_cards.committed_hint')"></i>
        </p>
        <p class="text-lg leading-snug md:text-xl font-semibold text-orange-600 dark:text-orange-400">
          <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_committed) }}
        </p>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl px-3 py-2.5 md:p-4 border max-md:dark:bg-surface-900 border-surface-200 dark:border-surface-700">
        <p class="text-sm max-md:text-[0.8125rem] text-surface-500 dark:text-surface-400 md:mb-1">
          {{ t('band_space_finance.status.planned') }}
          <i class="pi pi-info-circle text-xs ml-1 cursor-help max-md:hidden!" v-tooltip.top="t('band_space_finance.metric_cards.planned_hint')"></i>
        </p>
        <p class="text-lg leading-snug md:text-xl font-semibold text-blue-600 dark:text-blue-400">
          <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_planned) }}
        </p>
      </div>

      <div v-if="summary?.total_personal > 0" class="max-md:col-span-2 bg-surface-0 dark:bg-surface-800 rounded-xl px-3 py-2.5 md:p-4 border max-md:dark:bg-surface-900 border-surface-200 dark:border-surface-700">
        <p class="text-sm max-md:text-[0.8125rem] text-surface-500 dark:text-surface-400 md:mb-1">
          {{ t('band_space_finance.metric_cards.personal') }}
          <i class="pi pi-info-circle text-xs ml-1 cursor-help max-md:hidden!" v-tooltip.top="t('band_space_finance.metric_cards.personal_hint')"></i>
        </p>
        <!-- Incomes and expenses apart, never added up (#1168) -->
        <FinanceTypeTotals :totals="personalTotals" class="text-lg leading-snug md:text-xl font-semibold flex-wrap" />
      </div>
    </div>

    <!-- A phone has no hover: the tooltips' wording is one tap away, all together. -->
    <template v-if="isBelowMd">
      <button
        type="button"
        class="mt-2 flex items-center gap-1.5 py-1 text-[0.8125rem] font-semibold text-primary-700 dark:text-primary-300"
        aria-haspopup="dialog"
        @click="isHintsSheetOpen = true"
      >
        <i class="pi pi-info-circle text-xs" aria-hidden="true" />
        {{ t('band_space_finance.phone.totals_help') }}
      </button>
      <BottomSheet v-model:visible="isHintsSheetOpen" :title="t('band_space_finance.phone.totals_help')">
        <dl class="flex flex-col gap-4 px-5 pb-2">
          <div v-for="hint in hints" :key="hint.label">
            <dt class="font-semibold" :class="hint.color">{{ hint.label }}</dt>
            <dd class="text-sm text-surface-700 dark:text-surface-300">{{ hint.text }}</dd>
          </div>
        </dl>
      </BottomSheet>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { formatAmount } from '../../../utils/currency.js'
import BottomSheet from '../../Global/BottomSheet.vue'
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

const isBelowMd = useIsBelowMd()
const isHintsSheetOpen = ref(false)

const hints = computed(() => {
  const rows = [
    {
      label: t('band_space_finance.totals.income'),
      text: t('band_space_finance.metric_cards.income_hint'),
      color: 'text-green-700 dark:text-green-400'
    },
    {
      label: t('band_space_finance.totals.expense'),
      text: t('band_space_finance.metric_cards.expense_hint'),
      color: 'text-red-600 dark:text-red-400'
    },
    {
      label: t('band_space_finance.status.committed'),
      text: t('band_space_finance.metric_cards.committed_hint'),
      color: 'text-orange-600 dark:text-orange-400'
    },
    {
      label: t('band_space_finance.status.planned'),
      text: t('band_space_finance.metric_cards.planned_hint'),
      color: 'text-blue-600 dark:text-blue-400'
    }
  ]
  if (props.summary?.total_personal > 0) {
    rows.push({
      label: t('band_space_finance.metric_cards.personal'),
      text: t('band_space_finance.metric_cards.personal_hint'),
      color: ''
    })
  }
  return rows
})
</script>
