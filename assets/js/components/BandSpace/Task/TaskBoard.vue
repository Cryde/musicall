<template>
  <div class="flex gap-4 overflow-x-auto pb-2">
    <TaskColumn
      v-for="col in columns"
      :key="col.status"
      :status="col.status"
      :tasks="col.tasks"
      :label="col.label"
      :categories="categories"
      :band-space-id="bandSpaceId"
      @open-task="$emit('open-task', $event)"
      @reorder="(status, ids, movedId) => $emit('reorder', status, ids, movedId)"
      @status-change="
        (taskId, status, visibleIndex) => $emit('status-change', taskId, status, visibleIndex)
      "
      @show-all-done="$emit('show-all-done')"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import TaskColumn from './TaskColumn.vue'

const props = defineProps({
  tasksByStatus: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  bandSpaceId: { type: String, required: true }
})

defineEmits(['open-task', 'reorder', 'status-change', 'show-all-done'])

const { t } = useI18n()

const columns = computed(() => [
  {
    status: 'todo',
    label: t('band_space_tasks.status.todo'),
    tasks: props.tasksByStatus.todo || []
  },
  {
    status: 'in_progress',
    label: t('band_space_tasks.status.in_progress'),
    tasks: props.tasksByStatus.in_progress || []
  },
  {
    status: 'done',
    label: t('band_space_tasks.status.done'),
    tasks: props.tasksByStatus.done || []
  }
])
</script>
