<template>
  <div v-if="entries.length === 0" class="text-sm text-surface-400 italic py-1">
    Aucune entrée
  </div>
  <div
    v-for="entry in entries"
    :key="entry.id"
    class="group flex items-center gap-1.5 sm:gap-2 py-2 px-1 sm:px-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
    @click="canEdit(entry) && emit('edit', entry)"
  >
    <!-- Status dot -->
    <span
      class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full flex-shrink-0"
      :class="statusDotClass(entry.status)"
      :title="statusLabel(entry.status)"
    ></span>

    <!-- Recurrence icon -->
    <i
      v-if="entry.recurrence_id"
      class="pi pi-sync text-xs text-surface-400 flex-shrink-0"
      title="Récurrence"
    ></i>

    <!-- Label -->
    <span class="flex-1 text-sm truncate min-w-0">{{ entry.label }}</span>

    <!-- Date (hidden on mobile) -->
    <span class="hidden sm:inline text-xs text-surface-400 flex-shrink-0">{{ formatDateCompact(entry.date) }}</span>

    <!-- Scope badge -->
    <span
      v-if="entry.scope === 'personal'"
      class="text-xs font-medium px-1.5 py-0.5 rounded-full flex-shrink-0 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
    >
      Perso
    </span>

    <!-- Type badge (hidden on mobile) -->
    <span
      class="hidden sm:inline text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
      :class="typeBadgeClass(entry.type)"
    >
      {{ entry.type === 'expense' ? 'Dépense' : 'Revenu' }}
    </span>

    <!-- Amount -->
    <span class="text-sm font-medium tabular-nums flex-shrink-0 text-right w-20 sm:w-24">
      {{ formatEntryAmount(entry) }}
    </span>

    <!-- Edit button (visible on hover for desktop, always visible on mobile) -->
    <button
      v-if="canEdit(entry)"
      class="sm:opacity-0 sm:group-hover:opacity-100 transition-opacity text-surface-400 hover:text-primary flex-shrink-0 p-1"
      title="Modifier"
      @click.stop="emit('edit', entry)"
    >
      <i class="pi pi-pencil text-sm"></i>
    </button>
  </div>
</template>

<script setup>
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompact } from '../../../utils/date.js'

const props = defineProps({
  entries: { type: Array, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits(['edit'])

function canEdit(entry) {
  if (entry.scope !== 'personal') return true
  return entry.member_id === props.currentMembershipId
}

function formatEntryAmount(entry) {
  if (entry.amount != null) {
    return formatAmount(entry.amount)
  }
  if (entry.amount_min != null && entry.amount_max != null) {
    return `${formatAmount(entry.amount_min)} - ${formatAmount(entry.amount_max)}`
  }
  return '0,00 \u20AC'
}

function statusDotClass(status) {
  switch (status) {
    case 'paid':
      return 'bg-green-500'
    case 'committed':
      return 'bg-orange-500'
    default:
      return 'bg-surface-400'
  }
}

function statusLabel(status) {
  switch (status) {
    case 'paid':
      return 'Payé'
    case 'committed':
      return 'Engagé'
    default:
      return 'Prévu'
  }
}

function typeBadgeClass(type) {
  return type === 'expense'
    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
}
</script>
