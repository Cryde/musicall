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
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { TASK_STATUSES } from '../../../constants/taskStatuses.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import TaskStatusMark from './TaskStatusMark.vue'

/**
 * What a task's mark opens on a phone: its three statuses, the current one ticked. A move goes to the
 * end of the chosen status, as the desktop card's « Déplacer vers » does.
 */
const props = defineProps({
  task: { type: Object, default: null },
  bandSpaceId: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const toast = useToast()
const tasksStore = useBandTasksStore()

async function choose(status) {
  const task = props.task
  visible.value = false
  if (task.status === status) return

  try {
    await tasksStore.moveTaskToColumn(props.bandSpaceId, task.id, status, null)
  } catch {
    toast.add({ severity: 'error', summary: t('band_space_tasks.common.move_failed'), life: 5000 })
  }
}
</script>
