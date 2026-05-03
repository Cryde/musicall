<template>
  <div class="p-4">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Agenda</h1>
    </div>

    <div v-if="agendaStore.isLoading" class="flex items-center justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="agendaStore.loadError" class="text-center text-red-500 py-12">
      {{ agendaStore.loadError }}
    </div>

    <div
      v-else-if="agendaStore.items.length === 0"
      class="text-center text-surface-400 italic py-12"
    >
      Aucun événement à venir dans les 30 prochains jours
    </div>

    <div v-else>
      <div v-for="group in groupedItems" :key="group.date" class="mb-6">
        <div class="flex items-center gap-2 mb-2 px-2">
          <span class="text-sm font-semibold text-surface-600 dark:text-surface-300">
            {{ formatDateLabel(group.date) }}
          </span>
          <span class="text-xs text-surface-400">
            {{ group.items.length }} événement{{ group.items.length > 1 ? 's' : '' }}
          </span>
        </div>
        <div
          class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden"
        >
          <div
            v-for="item in group.items"
            :key="item.id"
            class="flex items-start gap-3 p-3 border-l-4 border-b border-surface-200 dark:border-surface-700 last:border-b-0"
            :class="sourceBorderClass(item.source)"
          >
            <span class="text-sm font-medium tabular-nums text-surface-600 dark:text-surface-300 w-12 flex-shrink-0 pt-0.5">
              {{ formatTime(item.datetime) }}
            </span>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium truncate">{{ item.title }}</span>
                <span
                  class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
                  :class="sourceBadgeClass(item.source)"
                >
                  {{ sourceLabel(item.source) }}
                </span>
              </div>

              <div
                v-if="item.description"
                class="text-sm text-surface-500 dark:text-surface-400 mt-0.5 line-clamp-2"
              >
                {{ item.description }}
              </div>

              <div
                v-if="metadataLine(item)"
                class="text-xs text-surface-400 mt-1"
              >
                {{ metadataLine(item) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import ProgressSpinner from 'primevue/progressspinner'
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBandAgendaStore } from '../../store/bandSpace/bandSpaceAgenda.js'
import { formatDateCompactWithYear } from '../../utils/date.js'

const route = useRoute()
const agendaStore = useBandAgendaStore()

const groupedItems = computed(() => {
  const groups = new Map()
  for (const item of agendaStore.items) {
    const date = item.datetime.substring(0, 10)
    if (!groups.has(date)) {
      groups.set(date, [])
    }
    groups.get(date).push(item)
  }
  return Array.from(groups, ([date, items]) => ({ date, items }))
})

onMounted(() => {
  agendaStore.fetchAgenda(route.params.id)
})

watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId && newId !== oldId) {
      agendaStore.clear()
      agendaStore.fetchAgenda(newId)
    }
  }
)

function formatDateLabel(dateString) {
  return formatDateCompactWithYear(dateString)
}

function formatTime(datetimeString) {
  return datetimeString.substring(11, 16)
}

function sourceLabel(source) {
  switch (source) {
    case 'manual':
      return 'Manuel'
    case 'task':
      return 'Tâche'
    case 'finance':
      return 'Finance'
    default:
      return source
  }
}

function sourceBorderClass(source) {
  switch (source) {
    case 'manual':
      return 'border-l-blue-500'
    case 'task':
      return 'border-l-amber-500'
    case 'finance':
      return 'border-l-emerald-500'
    default:
      return 'border-l-surface-300'
  }
}

function sourceBadgeClass(source) {
  switch (source) {
    case 'manual':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'task':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    case 'finance':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    default:
      return 'bg-surface-100 text-surface-600'
  }
}

function metadataLine(item) {
  const parts = []
  if (item.source === 'manual' && item.metadata?.location) {
    parts.push(item.metadata.location)
  }
  if (item.source === 'task') {
    if (item.metadata?.category_name) parts.push(item.metadata.category_name)
    if (item.metadata?.priority && item.metadata.priority !== 'normal') {
      parts.push(taskPriorityLabel(item.metadata.priority))
    }
  }
  if (item.source === 'finance') {
    if (item.metadata?.category_name) parts.push(item.metadata.category_name)
    if (item.metadata?.type) {
      parts.push(item.metadata.type === 'expense' ? 'Dépense' : 'Revenu')
    }
  }
  return parts.join(' · ')
}

function taskPriorityLabel(priority) {
  switch (priority) {
    case 'high':
      return 'Priorité haute'
    case 'urgent':
      return 'Urgent'
    default:
      return priority
  }
}
</script>
