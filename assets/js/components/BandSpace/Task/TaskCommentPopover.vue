<template>
  <Popover ref="popoverRef" @show="handleShow">
    <div class="w-80 max-w-sm">
      <div class="px-4 py-2 border-b border-surface-200 dark:border-surface-700">
        <span class="text-xs font-semibold text-surface-500 dark:text-surface-400 uppercase tracking-wider">
          Commentaires
        </span>
      </div>

      <div v-if="isLoading" class="p-4 flex flex-col gap-3">
        <Skeleton v-for="i in 3" :key="i" width="100%" height="3rem" borderRadius="0.375rem" />
      </div>

      <div v-else-if="error" class="p-4 text-sm text-red-500">
        {{ error }}
      </div>

      <div v-else class="max-h-96 overflow-y-auto p-4">
        <TaskCommentList :comments="comments" :members="tasksStore.members" />
      </div>

      <div class="border-t border-surface-200 dark:border-surface-700 p-2">
        <Button
          label="Ouvrir la tâche"
          icon="pi pi-external-link"
          text
          size="small"
          class="w-full"
          @click="handleOpenTask"
        />
      </div>
    </div>
  </Popover>
</template>

<script setup>
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import Skeleton from 'primevue/skeleton'
import { ref } from 'vue'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import TaskCommentList from './TaskCommentList.vue'

const props = defineProps({
  taskId: { type: String, required: true },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['open-task'])

const tasksStore = useBandTasksStore()
const popoverRef = ref()
const comments = ref([])
const isLoading = ref(false)
const error = ref(null)

async function handleShow() {
  isLoading.value = true
  error.value = null
  try {
    comments.value = await bandSpaceTasksApi.getComments(props.bandSpaceId, props.taskId)
  } catch {
    error.value = 'Impossible de charger les commentaires'
  } finally {
    isLoading.value = false
  }
}

function handleOpenTask() {
  emit('open-task', props.taskId)
  popoverRef.value?.hide()
}

defineExpose({
  toggle: (event) => popoverRef.value?.toggle(event),
  hide: () => popoverRef.value?.hide()
})
</script>
