<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        Revenus
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="'Total des revenus du groupe avec le statut Payé'"></i>
      </p>
      <p class="text-xl font-semibold text-green-600 dark:text-green-400">{{ formatAmount(summary?.total_income) }}</p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        Dépenses
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="'Total des dépenses du groupe avec le statut Payé'"></i>
      </p>
      <p class="text-xl font-semibold text-red-600 dark:text-red-400">{{ formatAmount(summary?.total_expense) }}</p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        Engagé
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="'Total des entrées du groupe avec le statut Engagé (dépenses + revenus). Les fourchettes utilisent la moyenne.'"></i>
      </p>
      <p class="text-xl font-semibold text-orange-600 dark:text-orange-400">
        <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_committed) }}
      </p>
    </div>

    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        Prévu
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="'Total des entrées du groupe avec le statut Prévu (dépenses + revenus). Les fourchettes utilisent la moyenne.'"></i>
      </p>
      <p class="text-xl font-semibold text-blue-600 dark:text-blue-400">
        <span v-if="summary?.has_estimates" class="text-sm font-normal">≈ </span>{{ formatAmount(summary?.total_planned) }}
      </p>
    </div>

    <div v-if="summary?.total_personal > 0" class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
        Personnel
        <i class="pi pi-info-circle text-xs ml-1 cursor-help" v-tooltip.top="'Total des entrées personnelles de tous les membres, tous statuts confondus. Non inclus dans les totaux du groupe.'"></i>
      </p>
      <p class="text-xl font-semibold text-purple-600 dark:text-purple-400">{{ formatAmount(summary?.total_personal) }}</p>
    </div>
  </div>
</template>

<script setup>
import { formatAmount } from '../../../utils/currency.js'

defineProps({
  summary: { type: Object, default: null }
})
</script>
