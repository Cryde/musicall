<template>
  <DashboardWidget
    title="Finances - ce mois"
    icon="pi pi-wallet"
    :is-loading="isLoading"
    :error="error"
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_finance', params: { id: bandSpaceId } }"
        class="text-xs text-primary hover:underline"
      >
        Voir les finances
      </RouterLink>
    </template>

    <div v-if="summary" class="flex flex-col gap-3">
      <div class="flex justify-between items-baseline pb-2 border-b border-surface-200 dark:border-surface-700">
        <span class="text-sm text-surface-600 dark:text-surface-300">Solde prévu</span>
        <span class="text-xl font-semibold tabular-nums" :class="balanceClass">{{ formatAmount(balance) }}</span>
      </div>
      <div class="flex justify-between items-center text-sm">
        <span class="flex items-center gap-2 text-surface-600 dark:text-surface-300">
          <i class="pi pi-arrow-up text-green-500" aria-hidden="true" />
          Revenus
        </span>
        <span class="tabular-nums text-green-700 dark:text-green-400">{{ formatAmount(summary.total_income_all) }}</span>
      </div>
      <div class="flex justify-between items-center text-sm">
        <span class="flex items-center gap-2 text-surface-600 dark:text-surface-300">
          <i class="pi pi-arrow-down text-red-500" aria-hidden="true" />
          Dépenses
        </span>
        <span class="tabular-nums text-red-600 dark:text-red-400">{{ formatAmount(summary.total_expense_all) }}</span>
      </div>
      <p v-if="paidShare" class="text-xs text-surface-500 dark:text-surface-400 mt-1">
        Dont {{ formatAmount(summary.total_paid) }} déjà payés.
      </p>
    </div>
  </DashboardWidget>
</template>

<script setup>
import { endOfMonth, format, startOfMonth } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceFinanceApi from '../../../api/bandSpace/band-space-finance.js'
import { formatAmount } from '../../../utils/currency.js'
import DashboardWidget from './DashboardWidget.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const summary = ref(null)
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const today = new Date()
    const from = format(startOfMonth(today), 'yyyy-MM-dd')
    const to = format(endOfMonth(today), 'yyyy-MM-dd')
    summary.value = await bandSpaceFinanceApi.getSummary(props.bandSpaceId, from, to)
  } catch {
    error.value = 'Finances indisponibles.'
  } finally {
    isLoading.value = false
  }
})

const balance = computed(
  () => (summary.value?.total_income_all ?? 0) - (summary.value?.total_expense_all ?? 0)
)

const balanceClass = computed(() => {
  if (balance.value > 0) return 'text-green-700 dark:text-green-400'
  if (balance.value < 0) return 'text-red-600 dark:text-red-400'
  return 'text-surface-700 dark:text-surface-200'
})

const paidShare = computed(() => (summary.value?.total_paid ?? 0) > 0)
</script>
