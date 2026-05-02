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
      @reorder="(status, ids) => $emit('reorder', status, ids)"
      @status-change="(taskId, status, newIndex) => $emit('status-change', taskId, status, newIndex)"
      @show-all-done="$emit('show-all-done')"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TaskColumn from './TaskColumn.vue'

const props = defineProps({
  tasksByStatus: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  bandSpaceId: { type: String, required: true }
})

defineEmits(['open-task', 'reorder', 'status-change', 'show-all-done'])

const columns = computed(() => [
  { status: 'todo', label: 'À faire', tasks: props.tasksByStatus.todo || [] },
  { status: 'in_progress', label: 'En cours', tasks: props.tasksByStatus.in_progress || [] },
  { status: 'done', label: 'Terminé', tasks: props.tasksByStatus.done || [] }
])
</script>
