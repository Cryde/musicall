<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-sm font-semibold text-surface-600 dark:text-surface-300 uppercase tracking-wide">Récurrences</h3>
      <button
        class="text-sm text-primary hover:underline"
        @click="emit('add')"
      >
        + Nouvelle récurrence
      </button>
    </div>

    <div v-if="recurrences.length === 0" class="text-sm text-surface-400 italic py-1">
      Aucune récurrence
    </div>

    <div
      v-for="recurrence in recurrences"
      :key="recurrence.id"
      class="py-2 px-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
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
          {{ recurrence.is_active ? 'Actif' : 'Inactif' }}
        </span>
      </div>
      <div class="flex items-center gap-2 mt-1 text-xs text-surface-500">
        <span class="font-medium px-1.5 py-0.5 rounded-full bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-300">
          {{ intervalLabels[recurrence.interval] ?? recurrence.interval }}
        </span>
        <span class="font-medium text-sm text-surface-700 dark:text-surface-200">{{ formatRecurrenceAmount(recurrence) }}</span>
        <span>{{ formatDateCompactWithYear(recurrence.start_date) }} – {{ formatDateCompactWithYear(recurrence.end_date) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'

defineProps({
  recurrences: { type: Array, required: true }
})

const emit = defineEmits(['edit', 'add'])

const intervalLabels = {
  weekly: 'Hebdo',
  monthly: 'Mensuel',
  quarterly: 'Trim.',
  yearly: 'Annuel'
}

function formatRecurrenceAmount(recurrence) {
  return formatAmount(recurrence.amount)
}
</script>
