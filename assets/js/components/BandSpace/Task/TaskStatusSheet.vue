<template>
  <BottomSheet v-model:visible="visible" :title="task?.title ?? ''">
    <ul v-if="task" class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li v-for="status in TASK_STATUSES" :key="status.value">
        <button
          type="button"
          class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-surface-900 transition-colors hover:bg-surface-100 dark:text-surface-0 dark:hover:bg-surface-800"
          :aria-pressed="task.status === status.value"
          @click="choose(status.value)"
        >
          <TaskStatusMark :status="status.value" />
          {{ t(status.labelKey) }}
          <i v-if="task.status === status.value" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </BottomSheet>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { TASK_STATUSES } from '../../../constants/taskStatuses.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import TaskStatusMark from './TaskStatusMark.vue'

/**
 * What a task's mark opens on a phone: its three statuses, the current one ticked. It only says which
 * one was chosen: the list moves the task to the end of that status, the task screen saves its field.
 */
const props = defineProps({
  task: { type: Object, default: null }
})

const emit = defineEmits(['choose'])

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()

function choose(status) {
  visible.value = false
  if (props.task.status !== status) emit('choose', status)
}
</script>
