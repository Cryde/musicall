<template>
  <div class="flex-1 min-w-[280px] bg-surface-100 dark:bg-surface-800 rounded-xl p-3">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-semibold text-sm text-surface-700 dark:text-surface-200">{{ label }}</h3>
      <span class="text-xs text-surface-400 bg-surface-200 dark:bg-surface-700 rounded-full px-2 py-0.5">
        {{ tasks.length }}
      </span>
    </div>
    <VueDraggable
      v-model="localTasks"
      group="tasks"
      :animation="200"
      :disabled="tasksStore.isSelectionMode"
      ghost-class="opacity-30"
      :data-status="status"
      class="flex flex-col gap-2 min-h-[100px]"
      @end="handleDragEnd"
    >
      <TaskCard
        v-for="task in visibleTasks"
        :key="task.id"
        :task="task"
        :band-space-id="bandSpaceId"
        :category-color="getCategoryColor(task.category_id)"
        @open-task="$emit('open-task', $event)"
      />
    </VueDraggable>

    <button
      v-if="isDoneColumn && hiddenCount > 0"
      class="w-full mt-2 text-xs text-primary font-medium py-2 rounded-lg hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
      @click="$emit('show-all-done')"
    >
      Voir les {{ tasks.length }} tâches terminées
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import TaskCard from './TaskCard.vue'

const tasksStore = useBandTasksStore()

const MAX_DONE_VISIBLE = 5

const props = defineProps({
  status: { type: String, required: true },
  tasks: { type: Array, required: true },
  label: { type: String, required: true },
  categories: { type: Array, default: () => [] },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['open-task', 'reorder', 'status-change', 'show-all-done'])

const localTasks = ref([...props.tasks])

watch(
  () => props.tasks,
  (newTasks) => {
    localTasks.value = [...newTasks]
  }
)

const isDoneColumn = computed(() => props.status === 'done')

const visibleTasks = computed(() => {
  if (!isDoneColumn.value) return localTasks.value
  const sorted = [...localTasks.value].sort((a, b) => {
    const dateA = a.completed_datetime || a.update_datetime || a.creation_datetime
    const dateB = b.completed_datetime || b.update_datetime || b.creation_datetime
    return new Date(dateB) - new Date(dateA)
  })
  return sorted.slice(0, MAX_DONE_VISIBLE)
})

const hiddenCount = computed(() => {
  if (!isDoneColumn.value) return 0
  return Math.max(0, localTasks.value.length - MAX_DONE_VISIBLE)
})

function getCategoryColor(categoryId) {
  if (!categoryId) return null
  const cat = props.categories.find((c) => c.id === categoryId)
  return cat?.color || null
}

function handleDragEnd(event) {
  const taskId = event.item?.dataset?.taskId
  if (!taskId) return

  const fromStatus = event.from?.dataset?.status
  const toStatus = event.to?.dataset?.status

  if (fromStatus === toStatus) {
    // Same-column reorder: localTasks is already updated by v-model
    const orderedIds = localTasks.value.map((t) => t.id)
    emit('reorder', props.status, orderedIds)
  } else {
    // Cross-column move: @end fires on the source column
    // Pass the drop index so the store can compute the correct order
    emit('status-change', taskId, toStatus, event.newIndex)
  }
}
</script>
