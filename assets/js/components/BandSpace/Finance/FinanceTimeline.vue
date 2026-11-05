<template>
  <!-- On a phone the dates come straight after the totals: « Ajouter une entrée » floats at the
       bottom of the page, and categories are added from their own view. -->
  <div v-if="!isBelowMd" class="flex justify-end gap-2 mb-4">
    <Button
      icon="pi pi-plus"
      :label="t('band_space_finance.common.add_category')"
      size="small"
      outlined
      @click="emit('add-category')"
    />
    <Button
      icon="pi pi-plus"
      :label="t('band_space_finance.common.add_entry')"
      size="small"
      outlined
      @click="emit('add-entry')"
    />
  </div>
  <div v-if="entries.length === 0" class="text-center text-surface-400 italic py-8">
    {{ t('band_space_finance.timeline.empty') }}
  </div>
  <div v-else>
    <div v-for="group in groupedEntries" :key="group.date" class="mb-4 max-md:mb-0 max-md:mt-4">
      <div class="flex items-center gap-2 mb-1 px-2 max-md:mb-1.5 max-md:px-1">
        <span class="text-sm font-semibold text-surface-600 dark:text-surface-300 max-md:text-surface-800 max-md:dark:text-surface-100">{{ formatDateLabel(group.date) }}</span>
        <span class="text-xs text-surface-400">{{ t('band_space_finance.common.entry_count', group.entries.length) }}</span>
      </div>
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 px-2 py-1 max-md:overflow-hidden max-md:rounded-2xl max-md:p-0 max-md:dark:bg-surface-900 max-md:divide-y max-md:divide-surface-200 dark:max-md:divide-surface-700">
        <template v-if="isBelowMd">
          <FinanceEntryPhoneRow
            v-for="entry in group.entries"
            :key="entry.id"
            :entry="entry"
            :meta="`${categoryName(entry.category_id)} · ${typeLabel(entry.type)}`"
            :can-open="canEdit(entry)"
            @open="emit('edit-entry', entry)"
          />
        </template>
        <div
          v-for="entry in group.entries"
          v-else
          :key="entry.id"
          class="group flex items-center gap-1.5 sm:gap-2 py-2 px-1 sm:px-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
          @click="canEdit(entry) && emit('edit-entry', entry)"
        >
          <!-- Status indicator -->
          <FinanceStatusDot :status="entry.status" />

          <!-- Recurrence icon -->
          <i
            v-if="entry.recurrence_id"
            class="pi pi-sync text-xs text-surface-400 flex-shrink-0"
            :title="t('band_space_finance.common.recurrence')"
          ></i>

          <!-- Label -->
          <span class="flex-1 text-sm truncate min-w-0">{{ entry.label }}</span>

          <!-- Category name -->
          <span class="hidden sm:inline text-xs text-surface-400 bg-surface-100 dark:bg-surface-700 px-1.5 py-0.5 rounded flex-shrink-0">
            {{ categoryName(entry.category_id) }}
          </span>

          <!-- Scope badge -->
          <span
            v-if="entry.scope === 'personal'"
            class="text-xs font-medium px-1.5 py-0.5 rounded-full flex-shrink-0 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
          >
            {{ t('band_space_finance.common.personal_badge') }}
          </span>

          <!-- Type badge (hidden on mobile) -->
          <span
            class="hidden sm:inline text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
            :class="typeBadgeClass(entry.type)"
          >
            {{ typeLabel(entry.type) }}
          </span>

          <!-- Répartition that no longer matches the amount, against the amount it disagrees with -->
          <FinanceSplitWarning v-if="entry.split_warning" />

          <!-- Amount -->
          <span class="text-sm font-medium tabular-nums flex-shrink-0 text-right w-20 sm:w-24">
            {{ formatEntryAmount(entry) }}
          </span>

          <!-- Edit button -->
          <button
            v-if="canEdit(entry)"
            class="sm:opacity-0 sm:group-hover:opacity-100 transition-opacity text-surface-400 hover:text-primary flex-shrink-0 p-1"
            :title="t('band_space_finance.common.edit')"
            :aria-label="t('band_space_finance.common.edit')"
            @click.stop="emit('edit-entry', entry)"
          >
            <i class="pi pi-pencil text-sm"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'
import { formatEntryAmount } from '../../../utils/financeEntryAmount.js'
import FinanceEntryPhoneRow from './FinanceEntryPhoneRow.vue'
import FinanceSplitWarning from './FinanceSplitWarning.vue'
import FinanceStatusDot from './FinanceStatusDot.vue'

const { t } = useI18n()
const isBelowMd = useIsBelowMd()

const props = defineProps({
  entries: { type: Array, required: true },
  categories: { type: Array, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits(['edit-entry', 'add-entry', 'add-category'])

const categoryMap = computed(() => {
  const map = new Map()
  for (const cat of props.categories) {
    map.set(cat.id, cat.name)
  }
  return map
})

const groupedEntries = computed(() => {
  const groups = new Map()
  for (const entry of props.entries) {
    const date = entry.date
    if (!groups.has(date)) {
      groups.set(date, [])
    }
    groups.get(date).push(entry)
  }
  return Array.from(groups, ([date, entries]) => ({ date, entries }))
})

function categoryName(categoryId) {
  return categoryMap.value.get(categoryId) ?? ''
}

function formatDateLabel(dateString) {
  return formatDateCompactWithYear(dateString)
}

function canEdit(entry) {
  if (entry.scope !== 'personal') return true
  return entry.member_id === props.currentMembershipId
}

function typeLabel(type) {
  return type === 'expense'
    ? t('band_space_finance.type.expense')
    : t('band_space_finance.type.income')
}

function typeBadgeClass(type) {
  return type === 'expense'
    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
}
</script>
