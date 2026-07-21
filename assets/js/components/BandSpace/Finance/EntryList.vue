<template>
  <div>
    <VueDraggable
      v-model="localEntries"
      group="finance-entries"
      :sort="false"
      :disabled="financeStore.isSaving"
      :animation="200"
      handle=".finance-entry-drag-handle"
      ghost-class="opacity-30"
      :data-category-id="categoryId"
      class="flex flex-col min-h-[1.75rem]"
      @end="handleDragEnd"
    >
      <div
        v-for="entry in localEntries"
        :key="entry.id"
        :data-entry-id="entry.id"
        class="group flex items-center gap-1.5 sm:gap-2 py-2 px-1 sm:px-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
        @click="canEdit(entry) && emit('edit', entry)"
      >
        <!-- Drag handle (editable, non-paid entries only) -->
        <span
          v-if="isDraggable(entry)"
          class="finance-entry-drag-handle flex-shrink-0 cursor-grab active:cursor-grabbing touch-none text-surface-300 hover:text-surface-500 dark:text-surface-600 dark:hover:text-surface-400 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity"
          title="Déplacer vers une autre catégorie"
          aria-label="Déplacer l'entrée"
          @click.stop
        >
          <i class="pi pi-bars text-xs"></i>
        </span>
        <!-- Paid entries are locked (settled): editable via the drawer but not draggable until set
             back to « Engagé ». Show a lock cue so the missing drag handle doesn't read as a bug. -->
        <span
          v-else-if="isPaidLocked(entry)"
          class="flex-shrink-0 cursor-not-allowed text-surface-300 dark:text-surface-600 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity"
          title="Entrée payée : repassez-la en « Engagé » pour la déplacer"
          aria-label="Entrée payée, non déplaçable"
        >
          <i class="pi pi-lock text-xs"></i>
        </span>

        <!-- Status indicator -->
        <FinanceStatusDot :status="entry.status" />

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
    </VueDraggable>
    <div v-if="localEntries.length === 0 && !hideEmptyState" class="text-sm text-surface-400 italic py-1">
      Aucune entrée
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompact } from '../../../utils/date.js'
import FinanceStatusDot from './FinanceStatusDot.vue'

const props = defineProps({
  entries: { type: Array, required: true },
  categoryId: { type: String, required: true },
  currentMembershipId: { type: String, default: null },
  // Pole-level lists of a pole that has subcategories hide the "Aucune entrée" text: the list still
  // renders as a drop target, but the empty label would read as a bug next to filled subcategories.
  hideEmptyState: { type: Boolean, default: false }
})

const emit = defineEmits(['edit', 'move'])

const financeStore = useBandSpaceFinanceStore()

// VueDraggable needs a mutable model, but the store's entries are readonly. Keep a local copy and
// re-sync whenever props change - after a move the store reloads, which is the authoritative state.
const localEntries = ref([...props.entries])
watch(
  () => props.entries,
  (value) => {
    localEntries.value = [...value]
  }
)

function canEdit(entry) {
  if (entry.scope !== 'personal') return true
  return entry.member_id === props.currentMembershipId
}

// Draggable requires edit rights AND a non-paid status: the backend rejects a category change on a
// paid entry (422), so paid rows stay clickable-to-edit but cannot be dragged.
function isDraggable(entry) {
  return canEdit(entry) && entry.status !== 'paid'
}

// Editable entry that can't be dragged only because it's paid (settled). Drives the lock cue that
// replaces the drag handle, hinting the entry must go back to « Engagé » before it can be moved.
function isPaidLocked(entry) {
  return canEdit(entry) && entry.status === 'paid'
}

function handleDragEnd(event) {
  const entryId = event.item?.dataset?.entryId
  const toCategoryId = event.to?.dataset?.categoryId
  const fromCategoryId = event.from?.dataset?.categoryId
  if (!entryId || !toCategoryId || fromCategoryId === toCategoryId) return
  emit('move', entryId, toCategoryId)
}

function formatEntryAmount(entry) {
  if (entry.amount != null) {
    return formatAmount(entry.amount)
  }
  if (entry.amount_min != null && entry.amount_max != null) {
    return `${formatAmount(entry.amount_min)} - ${formatAmount(entry.amount_max)}`
  }
  return '0,00 €'
}

function typeBadgeClass(type) {
  return type === 'expense'
    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
}
</script>
