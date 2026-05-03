<template>
  <div class="flex flex-col gap-2 mb-4">
    <!-- Row 1: search + date range + actions -->
    <div class="flex items-center gap-3 flex-wrap">
      <IconField class="w-64">
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="searchInput"
          placeholder="Rechercher…"
          size="small"
          class="w-full"
          @input="emitSearchDebounced"
        />
      </IconField>

      <DatePicker
        :model-value="dueDateRange"
        selectionMode="range"
        placeholder="Échéance"
        dateFormat="yy-mm-dd"
        size="small"
        showIcon
        showButtonBar
        :manualInput="false"
        :inputClass="'w-56 text-xs'"
        @update:model-value="emitDateRange"
      />

      <div class="flex-1"></div>

      <Button
        :label="tasksStore.isSelectionMode ? 'Annuler' : 'Sélectionner'"
        :icon="tasksStore.isSelectionMode ? 'pi pi-times' : 'pi pi-check-square'"
        size="small"
        :severity="tasksStore.isSelectionMode ? 'secondary' : 'secondary'"
        :outlined="!tasksStore.isSelectionMode"
        @click="toggleSelectionMode"
      />
      <Button
        icon="pi pi-cog"
        text
        rounded
        size="small"
        v-tooltip.bottom="'Gérer les catégories'"
        @click="$emit('open-categories')"
      />
      <Button
        label="Nouvelle tâche"
        icon="pi pi-plus"
        size="small"
        @click="$emit('create-task')"
      />
    </div>

    <!-- Row 2: filters -->
    <div class="flex items-center gap-x-4 gap-y-2 flex-wrap">
      <!-- Category filters -->
      <div v-if="categories.length > 0" class="flex items-center gap-1.5">
        <button
          v-for="cat in categories"
          :key="cat.id"
          class="text-xs font-medium px-2 py-1 rounded-full border transition-colors"
          :class="
            filters.categoryId === cat.id
              ? 'border-transparent text-white'
              : 'border-surface-200 dark:border-surface-600 text-surface-600 dark:text-surface-300 hover:border-surface-400'
          "
          :style="filters.categoryId === cat.id ? { backgroundColor: cat.color } : {}"
          @click="toggleFilter('categoryId', cat.id)"
        >
          <span
            v-if="filters.categoryId !== cat.id"
            class="inline-block w-2 h-2 rounded-full mr-1"
            :style="{ backgroundColor: cat.color }"
          ></span>
          {{ cat.name }}
        </button>
      </div>

      <!-- Assignee filters -->
      <div v-if="members.length > 0" class="flex items-center gap-1">
        <button
          v-for="member in members"
          :key="member.id"
          type="button"
          class="rounded-full transition-all"
          :class="
            filters.assigneeId === member.user_id
              ? 'ring-2 ring-primary'
              : 'hover:ring-1 hover:ring-surface-400'
          "
          :title="member.username"
          @click="toggleFilter('assigneeId', member.user_id)"
        >
          <Avatar
            :username="member.username"
            :picture-url="member.profile_picture_url"
            size="md"
          />
        </button>
      </div>

      <!-- Priority filters -->
      <div class="flex items-center gap-1">
        <button
          v-for="p in priorities"
          :key="p.value"
          class="text-xs px-2 py-1 rounded border transition-colors"
          :class="
            filters.priority === p.value
              ? p.activeClass
              : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
          "
          @click="toggleFilter('priority', p.value)"
        >
          {{ p.label }}
        </button>
      </div>

      <!-- Quick toggles -->
      <div class="flex items-center gap-1">
        <button
          class="text-xs px-2 py-1 rounded border transition-colors"
          :class="
            filters.myTasks
              ? 'bg-primary text-primary-contrast border-primary'
              : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
          "
          @click="$emit('update-filter', 'myTasks', !filters.myTasks)"
        >
          Mes tâches
        </button>
        <button
          class="text-xs px-2 py-1 rounded border transition-colors"
          :class="
            filters.overdue
              ? 'bg-red-600 text-white border-red-600'
              : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
          "
          @click="$emit('update-filter', 'overdue', !filters.overdue)"
        >
          <i class="pi pi-clock text-[10px] mr-1"></i>
          En retard
        </button>
        <button
          class="text-xs px-2 py-1 rounded border transition-colors"
          :class="
            filters.showArchived
              ? 'bg-surface-600 text-white border-surface-600'
              : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
          "
          @click="$emit('update-filter', 'showArchived', !filters.showArchived)"
        >
          <i class="pi pi-box text-[10px] mr-1"></i>
          Archivées
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useDebounceFn } from '@vueuse/core'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import Avatar from '../../User/Avatar.vue'

const tasksStore = useBandTasksStore()

function toggleSelectionMode() {
  if (tasksStore.isSelectionMode) {
    tasksStore.exitSelectionMode()
  } else {
    tasksStore.enterSelectionMode()
  }
}

const props = defineProps({
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  filters: { type: Object, required: true }
})

const emit = defineEmits(['update-filter', 'open-categories', 'create-task'])

const searchInput = ref(props.filters.query ?? '')

watch(
  () => props.filters.query,
  (val) => {
    if (val !== searchInput.value) searchInput.value = val ?? ''
  }
)

const emitSearchDebounced = useDebounceFn(() => {
  emit('update-filter', 'query', searchInput.value)
}, 300)

const priorities = [
  {
    value: 'urgent',
    label: 'Urgent',
    activeClass:
      'bg-red-100 text-red-700 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700'
  },
  {
    value: 'high',
    label: 'Haute',
    activeClass:
      'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-900 dark:text-orange-300 dark:border-orange-700'
  },
  {
    value: 'normal',
    label: 'Normal',
    activeClass:
      'bg-surface-200 text-surface-700 border-surface-400 dark:bg-surface-600 dark:text-surface-200'
  }
]

function toggleFilter(key, value) {
  emit('update-filter', key, props.filters[key] === value ? null : value)
}

const dueDateRange = computed(() => {
  const from = props.filters.dueDateFrom ? new Date(props.filters.dueDateFrom) : null
  const to = props.filters.dueDateTo ? new Date(props.filters.dueDateTo) : null
  if (!from && !to) return null
  return [from, to]
})

function formatDate(date) {
  if (!date) return null
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function emitDateRange(value) {
  const [from, to] = Array.isArray(value) ? value : [null, null]
  emit('update-filter', 'dueDateFrom', formatDate(from))
  emit('update-filter', 'dueDateTo', formatDate(to))
}
</script>
