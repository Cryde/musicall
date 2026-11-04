<template>
  <div class="flex flex-col gap-2 mb-4">
    <!-- Row 1: search + date range + actions -->
    <div class="flex items-center gap-3 flex-wrap">
      <IconField class="w-64">
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="searchInput"
          :placeholder="t('band_space_tasks.filter_bar.search_placeholder')"
          size="small"
          class="w-full"
          @input="searchSoon"
        />
      </IconField>

      <TaskDueRangePicker
        :from="filters.dueDateFrom"
        :to="filters.dueDateTo"
        @update-filter="(key, value) => $emit('update-filter', key, value)"
      />

      <div class="flex-1"></div>

      <Button
        :label="
          tasksStore.isSelectionMode
            ? t('band_space_tasks.common.cancel')
            : t('band_space_tasks.filter_bar.select')
        "
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
        v-tooltip.bottom="t('band_space_tasks.common.manage_categories')"
        :aria-label="t('band_space_tasks.common.manage_categories')"
        @click="$emit('open-categories')"
      />
      <Button
        :label="t('band_space_tasks.common.new_task')"
        icon="pi pi-plus"
        size="small"
        @click="$emit('create-task')"
      />
    </div>

    <!-- Row 2: filters -->
    <TaskFilterChips
      :categories="categories"
      :members="members"
      :filters="filters"
      @update-filter="(key, value) => $emit('update-filter', key, value)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { useI18n } from 'vue-i18n'
import { useTaskSearchInput } from '../../../composables/useTaskSearchInput.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import TaskDueRangePicker from './TaskDueRangePicker.vue'
import TaskFilterChips from './TaskFilterChips.vue'

const tasksStore = useBandTasksStore()
const { t } = useI18n()

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

const { searchInput, searchSoon } = useTaskSearchInput(
  () => props.filters.query,
  (text) => emit('update-filter', 'query', text)
)
</script>
