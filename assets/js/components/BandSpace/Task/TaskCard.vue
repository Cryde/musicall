<template>
  <div
    :data-task-id="task.id"
    class="bg-surface-0 dark:bg-surface-900 rounded-lg p-3 cursor-pointer hover:ring-1 hover:ring-primary-300 transition-shadow shadow-sm border"
    :class="
      isSelected
        ? 'ring-2 ring-primary border-primary'
        : 'border-surface-200 dark:border-surface-700'
    "
    @click="handleClick"
  >
    <div class="flex items-start gap-2">
      <Checkbox
        v-if="tasksStore.isSelectionMode"
        :model-value="isSelected"
        binary
        size="small"
        class="mt-0.5"
        @click.stop="tasksStore.toggleTaskSelection(task.id)"
      />
      <!-- On a phone the status is moved from here, in two taps, rather than by dragging a card across
           a screen that shows one status at a time. -->
      <button
        v-else-if="compact"
        type="button"
        class="-m-1 shrink-0 rounded-lg p-1 focus-visible:outline-2 focus-visible:outline-primary-500"
        :aria-label="t('band_space_tasks.phone.change_status', { title: task.title })"
        aria-haspopup="dialog"
        @click.stop="emit('pick-status', task)"
      >
        <TaskStatusMark :status="task.status" />
      </button>
      <!-- A button, so the card is reached by a keyboard and named by a screen reader; the card's own
           click stays for the mouse. -->
      <button
        type="button"
        class="flex-1 min-w-0 text-left font-medium text-sm [overflow-wrap:anywhere] text-surface-800 dark:text-surface-100 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
        @click.stop="handleClick"
      >
        {{ task.title }}
      </button>
    </div>

    <div v-if="hasBadges" class="flex flex-wrap gap-1.5 mt-2" :class="{ 'pl-8': compact }">
      <span
        v-if="task.priority === 'urgent'"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300"
      >
        {{ t('band_space_tasks.priority.urgent') }}
      </span>
      <span
        v-else-if="task.priority === 'high'"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300"
      >
        {{ t('band_space_tasks.priority.high') }}
      </span>
      <span
        v-if="task.category_name"
        class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
        :style="categoryStyle"
      >
        {{ task.category_name }}
      </span>
    </div>

    <div class="flex items-center justify-between mt-2" :class="{ 'pl-8': compact }">
      <div class="flex items-center -space-x-1.5">
        <div
          v-for="assignee in visibleAssignees"
          :key="assignee.id"
          class="rounded-full border-2 border-surface-0 dark:border-surface-900"
        >
          <Avatar
            :username="assignee.username"
            :display-name="assignee.display_name"
            :picture-url="assignee.profile_picture_url"
            size="sm"
          />
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
          :title="t('band_space_tasks.card.has_description')"
        />
        <span
          v-if="task.file_count > 0"
          class="flex items-center gap-1"
          :title="t('band_space_tasks.card.attached_files', task.file_count)"
        >
          <i class="pi pi-paperclip" />
          {{ task.file_count }}
        </span>
        <button
          v-if="task.comment_count > 0"
          type="button"
          class="flex items-center gap-1 hover:text-primary transition-colors"
          :title="t('band_space_tasks.card.comments', task.comment_count)"
          @click.stop="popoverRef?.toggle($event)"
        >
          <i class="pi pi-comment" />
          {{ task.comment_count }}
        </button>
        <TaskCommentPopover
          v-if="task.comment_count > 0"
          ref="popoverRef"
          :task-id="task.id"
          :band-space-id="bandSpaceId"
          @open-task="emit('open-task', $event)"
        />
        <span
          v-if="task.due_date"
          :class="isPastDue ? 'text-red-500 font-semibold' : ''"
        >
          {{ formattedDueDate }}
        </span>
        <!-- Its only items move the task, which the mark does on a phone. -->
        <button
          v-if="!compact"
          type="button"
          class="ml-1 w-7 h-7 -mr-1 rounded hover:bg-surface-100 dark:hover:bg-surface-800 flex items-center justify-center text-surface-500"
          :aria-label="t('band_space_tasks.card.more_actions')"
          aria-haspopup="menu"
          @click.stop="actionMenuRef?.toggle($event)"
        >
          <i class="pi pi-ellipsis-v text-sm" aria-hidden="true" />
        </button>
        <Menu v-if="!compact" ref="actionMenuRef" :model="actionMenuItems" :popup="true" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import Menu from 'primevue/menu'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { TASK_STATUSES } from '../../../constants/taskStatuses.js'
import { intlLocale } from '../../../i18n/formatLocale.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import Avatar from '../../User/Avatar.vue'
import TaskCommentPopover from './TaskCommentPopover.vue'
import TaskStatusMark from './TaskStatusMark.vue'

const props = defineProps({
  task: { type: Object, required: true },
  categoryColor: { type: String, default: null },
  bandSpaceId: { type: String, required: true },
  // The phone's list: a status mark in place of the « ⋮ » menu.
  compact: { type: Boolean, default: false }
})

const emit = defineEmits(['open-task', 'pick-status'])

const tasksStore = useBandTasksStore()
const toast = useToast()
const { t } = useI18n()
const popoverRef = ref()
const actionMenuRef = ref()

// Touch fallback for the drag-only kanban, and the only way to change a status while a
// server-side filter has drag turned off: build "Déplacer vers ..." items for the statuses that
// aren't the task's current one. There is no drop point, so the task goes to the end of the
// target column.
const actionMenuItems = computed(() => {
  return TASK_STATUSES.filter((s) => s.value !== props.task.status).map((s) => ({
    label: t('band_space_tasks.card.move_to', { status: t(s.labelKey) }),
    icon: 'pi pi-arrow-right',
    command: () => moveToStatus(s.value)
  }))
})

async function moveToStatus(newStatus) {
  try {
    await tasksStore.moveTaskToColumn(props.bandSpaceId, props.task.id, newStatus, null)
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space_tasks.common.move_failed'),
      life: 5000
    })
  }
}

const isSelected = computed(() => tasksStore.selectedTaskIds.has(props.task.id))

// Only drawn with something in it, or a card without a badge carries an empty line.
const hasBadges = computed(
  () => ['urgent', 'high'].includes(props.task.priority) || Boolean(props.task.category_name)
)

function handleClick() {
  if (tasksStore.isSelectionMode) {
    tasksStore.toggleTaskSelection(props.task.id)
  } else {
    emit('open-task', props.task.id)
  }
}

const MAX_AVATARS = 3

const visibleAssignees = computed(() => props.task.assignees.slice(0, MAX_AVATARS))
const overflowCount = computed(() => Math.max(0, props.task.assignees.length - MAX_AVATARS))

const categoryStyle = computed(() => {
  if (!props.categoryColor) return {}
  return { backgroundColor: `${props.categoryColor}20`, color: props.categoryColor }
})

const isPastDue = computed(() => {
  if (!props.task.due_date) return false
  return new Date(props.task.due_date) < new Date(new Date().toDateString())
})

const formattedDueDate = computed(() => {
  if (!props.task.due_date) return ''
  return new Date(props.task.due_date).toLocaleDateString(intlLocale(), {
    day: 'numeric',
    month: 'short'
  })
})
</script>
