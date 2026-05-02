<template>
  <div
    :data-task-id="task.id"
    class="bg-surface-0 dark:bg-surface-900 rounded-lg p-3 cursor-pointer hover:ring-1 hover:ring-primary-300 transition-shadow shadow-sm border border-surface-200 dark:border-surface-700"
  >
    <p class="font-medium text-sm text-surface-800 dark:text-surface-100">{{ task.title }}</p>

    <div class="flex flex-wrap gap-1.5 mt-2">
      <span
        v-if="task.priority === 'urgent'"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300"
      >
        Urgent
      </span>
      <span
        v-else-if="task.priority === 'high'"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300"
      >
        Haute
      </span>
      <span
        v-if="task.category_name"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
        :style="categoryStyle"
      >
        {{ task.category_name }}
      </span>
    </div>

    <div class="flex items-center justify-between mt-2">
      <div class="flex items-center -space-x-1.5">
        <div
          v-for="(assignee, index) in visibleAssignees"
          :key="assignee.id"
          class="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-primary-contrast text-[10px] font-semibold border-2 border-surface-0 dark:border-surface-900"
          :title="assignee.username"
        >
          {{ assignee.username.charAt(0).toUpperCase() }}
        </div>
        <div
          v-if="overflowCount > 0"
          class="w-6 h-6 rounded-full bg-surface-200 dark:bg-surface-700 flex items-center justify-center text-[10px] font-semibold text-surface-600 dark:text-surface-300 border-2 border-surface-0 dark:border-surface-900"
        >
          +{{ overflowCount }}
        </div>
      </div>

      <div class="flex items-center gap-2 text-xs text-surface-400">
        <i
          v-if="task.description"
          class="pi pi-align-left"
          title="Cette tâche a une description"
        />
        <span
          v-if="task.comment_count > 0"
          class="flex items-center gap-1"
          :title="`${task.comment_count} commentaire${task.comment_count > 1 ? 's' : ''}`"
        >
          <i class="pi pi-comment" />
          {{ task.comment_count }}
        </span>
        <span
          v-if="task.due_date"
          :class="isPastDue ? 'text-red-500 font-semibold' : ''"
        >
          {{ formattedDueDate }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  task: { type: Object, required: true },
  categoryColor: { type: String, default: null }
})

const MAX_AVATARS = 3

const visibleAssignees = computed(() => props.task.assignees.slice(0, MAX_AVATARS))
const overflowCount = computed(() => Math.max(0, props.task.assignees.length - MAX_AVATARS))

const categoryStyle = computed(() => {
  if (!props.categoryColor) return {}
  return { backgroundColor: props.categoryColor + '20', color: props.categoryColor }
})

const isPastDue = computed(() => {
  if (!props.task.due_date) return false
  return new Date(props.task.due_date) < new Date(new Date().toDateString())
})

const formattedDueDate = computed(() => {
  if (!props.task.due_date) return ''
  return new Date(props.task.due_date).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short'
  })
})
</script>
