<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="tasksStore.isLoading">
      <div class="flex gap-3 mb-4">
        <Skeleton v-for="i in 4" :key="i" width="5rem" height="1.75rem" borderRadius="9999px" />
        <div class="flex-1"></div>
        <Skeleton width="8rem" height="2rem" borderRadius="0.375rem" />
      </div>
      <div class="flex gap-4">
        <div v-for="i in 3" :key="i" class="flex-1 min-w-[280px] bg-surface-100 dark:bg-surface-800 rounded-xl p-3">
          <Skeleton width="40%" height="1rem" class="mb-3" />
          <div class="flex flex-col gap-2">
            <Skeleton v-for="j in 3" :key="j" width="100%" height="5rem" borderRadius="0.5rem" />
          </div>
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="tasksStore.loadError" class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4">
      <Message severity="error" :closable="false">{{ tasksStore.loadError }}</Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="handleRetry" />
    </div>

    <!-- Main content -->
    <div v-else>
      <TaskFilterBar
        :categories="tasksStore.categories"
        :members="tasksStore.members"
        :filters="tasksStore.filters"
        @update-filter="handleFilterUpdate"
        @open-categories="categoryManagerVisible = true"
        @create-task="createFormVisible = true"
      />

      <!-- Archived list view -->
      <div v-if="tasksStore.filters.showArchived">
        <div class="flex flex-col gap-2">
          <div
            v-for="task in tasksStore.archivedTasks"
            :key="task.id"
            class="flex items-center justify-between p-3 rounded-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-800 dark:text-surface-100 truncate">{{ task.title }}</p>
              <span class="text-xs text-surface-400">
                Archivée le {{ formatDate(task.archive_datetime) }}
              </span>
            </div>
            <Button
              label="Désarchiver"
              icon="pi pi-replay"
              text
              size="small"
              @click="handleUnarchive(task.id)"
            />
          </div>
          <p v-if="tasksStore.archivedTasks.length === 0" class="text-sm text-surface-400 italic text-center py-8">
            Aucune tâche archivée
          </p>
        </div>
      </div>

      <!-- All done list view -->
      <div v-else-if="showAllDone">
        <div class="flex items-center gap-2 mb-4">
          <Button
            icon="pi pi-arrow-left"
            text
            rounded
            size="small"
            @click="showAllDone = false"
          />
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
            Toutes les tâches terminées ({{ tasksStore.tasksByStatus.done.length }})
          </h3>
        </div>
        <div class="flex flex-col gap-2">
          <div
            v-for="task in tasksStore.tasksByStatus.done"
            :key="task.id"
            class="flex items-center justify-between p-3 rounded-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 cursor-pointer hover:ring-1 hover:ring-primary-300"
            @click="handleOpenTask(task.id)"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-800 dark:text-surface-100 truncate">{{ task.title }}</p>
              <span v-if="task.category_name" class="text-xs text-surface-400">{{ task.category_name }}</span>
            </div>
            <span class="text-xs text-surface-400">
              {{ formatDate(task.update_datetime || task.creation_datetime) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Kanban board -->
      <TaskBoard
        v-else
        :tasks-by-status="tasksStore.tasksByStatus"
        :categories="tasksStore.categories"
        @open-task="handleOpenTask"
        @reorder="handleReorder"
        @status-change="handleStatusChange"
        @show-all-done="showAllDone = true"
      />
    </div>

    <!-- Task detail drawer -->
    <TaskDetail
      v-model:visible="detailVisible"
      :task-id="tasksStore.activeTaskId"
      :band-space-id="bandSpaceId"
      @deleted="handleTaskDeleted"
    />

    <!-- Category manager drawer -->
    <TaskCategoryManager
      v-model:visible="categoryManagerVisible"
      :band-space-id="bandSpaceId"
    />

    <!-- Create task drawer -->
    <TaskCreateForm
      v-model:visible="createFormVisible"
      :band-space-id="bandSpaceId"
      @created="handleTaskCreated"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TaskBoard from '../../components/BandSpace/Task/TaskBoard.vue'
import TaskCategoryManager from '../../components/BandSpace/Task/TaskCategoryManager.vue'
import TaskCreateForm from '../../components/BandSpace/Task/TaskCreateForm.vue'
import TaskDetail from '../../components/BandSpace/Task/TaskDetail.vue'
import TaskFilterBar from '../../components/BandSpace/Task/TaskFilterBar.vue'
import { useBandTasksStore } from '../../store/bandSpace/bandSpaceTasks.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const tasksStore = useBandTasksStore()

const bandSpaceId = route.params.id
const detailVisible = ref(false)
const categoryManagerVisible = ref(false)
const createFormVisible = ref(false)
const showAllDone = ref(false)

// Deep link: ?task={id}
watch(
  () => route.query.task,
  (taskId) => {
    if (taskId) {
      tasksStore.setActiveTask(taskId, bandSpaceId)
      detailVisible.value = true
    } else {
      tasksStore.setActiveTask(null)
      detailVisible.value = false
    }
  },
  { immediate: true }
)

// Sync detail visibility back to URL
watch(detailVisible, (val) => {
  if (!val && route.query.task) {
    router.replace({ query: { ...route.query, task: undefined } })
  }
})

// Load archived tasks when toggle is activated
watch(
  () => tasksStore.filters.showArchived,
  (val) => {
    if (val) {
      tasksStore.fetchArchivedTasks(bandSpaceId)
      showAllDone.value = false
    }
  }
)

function handleOpenTask(taskId) {
  router.replace({ query: { ...route.query, task: taskId } })
}

function handleTaskDeleted() {
  router.replace({ query: { ...route.query, task: undefined } })
}

function handleTaskCreated() {
  // Task was added to store already
}

function handleFilterUpdate(key, value) {
  tasksStore.setFilter(key, value)
}

async function handleReorder(status, orderedIds) {
  try {
    await tasksStore.reorderTasks(bandSpaceId, status, orderedIds)
  } catch {
    toast.add({ severity: 'error', summary: 'Impossible de réordonner les tâches', life: 5000 })
  }
}

async function handleStatusChange(taskId, newStatus, newIndex) {
  try {
    await tasksStore.moveTaskToColumn(bandSpaceId, taskId, newStatus, newIndex)
  } catch {
    toast.add({ severity: 'error', summary: 'Impossible de déplacer la tâche', life: 5000 })
  }
}

async function handleUnarchive(taskId) {
  try {
    await tasksStore.unarchiveTask(bandSpaceId, taskId)
    toast.add({ severity: 'success', summary: 'Tâche désarchivée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function handleRetry() {
  tasksStore.fetchTasks(bandSpaceId)
  tasksStore.fetchCategories(bandSpaceId)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

onMounted(() => {
  tasksStore.fetchTasks(bandSpaceId)
  tasksStore.fetchCategories(bandSpaceId)
  tasksStore.fetchMembers(bandSpaceId)
})

onUnmounted(() => {
  tasksStore.clear()
})
</script>
