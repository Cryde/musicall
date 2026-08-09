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
          <!-- The row opens the drawer read-only: the description, the comments, the activity and
               the attachments of an archived task were otherwise unreachable without taking it out
               of the archive first. A button rather than a clickable div, so it is operable from
               the keyboard without borrowing a role from the one sitting next to it. -->
          <div
            v-for="task in tasksStore.archivedTasks"
            :key="task.id"
            class="flex items-center justify-between p-3 rounded-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700"
          >
            <button
              type="button"
              class="flex-1 min-w-0 text-left rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
              :aria-label="`Ouvrir la tâche archivée ${task.title}`"
              @click="handleOpenTask(task.id)"
            >
              <span class="block text-sm font-medium text-surface-800 dark:text-surface-100 truncate">
                {{ task.title }}
              </span>
              <span class="block text-xs text-surface-400">
                Archivée le {{ formatDate(task.archive_datetime) }}
              </span>
            </button>
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
            aria-label="Retour"
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
      <div v-else>
        <Message
          v-if="tasksStore.isReorderDisabled"
          severity="info"
          :closable="false"
          class="mb-3"
        >
          Réorganisation désactivée : la recherche, le filtre d'échéance et « En retard » masquent
          des tâches qui ne sont pas chargées, et un déplacement décalerait ces tâches masquées.
          Effacez ces filtres pour réordonner le tableau. Le menu d'une carte permet toujours de
          changer son statut.
        </Message>
        <TaskBoard
          :tasks-by-status="tasksStore.tasksByStatus"
          :categories="tasksStore.categories"
          :band-space-id="bandSpaceId"
          @open-task="handleOpenTask"
          @reorder="handleReorder"
          @status-change="handleStatusChange"
          @show-all-done="showAllDone = true"
        />
      </div>
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

    <!-- Bulk action bar (visible only in selection mode) -->
    <TaskBulkActionBar
      :band-space-id="bandSpaceId"
      :categories="tasksStore.categories"
      :members="tasksStore.members"
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
import TaskBulkActionBar from '../../components/BandSpace/Task/TaskBulkActionBar.vue'
import TaskCategoryManager from '../../components/BandSpace/Task/TaskCategoryManager.vue'
import TaskCreateForm from '../../components/BandSpace/Task/TaskCreateForm.vue'
import TaskDetail from '../../components/BandSpace/Task/TaskDetail.vue'
import TaskFilterBar from '../../components/BandSpace/Task/TaskFilterBar.vue'
import { useBandTasksStore } from '../../store/bandSpace/bandSpaceTasks.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const tasksStore = useBandTasksStore()
// Wipe any previous space's board synchronously before first render. The
// :key on <router-view> remounts this view on space switch but Pinia keeps
// A's tasks until cleared, which would flash for the duration of B's fetch.
tasksStore.clear()

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

// Refetch tasks from the server when any server-side filter changes
watch(
  () => [
    tasksStore.filters.query,
    tasksStore.filters.dueDateFrom,
    tasksStore.filters.dueDateTo,
    tasksStore.filters.overdue
  ],
  () => {
    tasksStore.fetchTasks(bandSpaceId)
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

/**
 * The server refuses a payload that no longer matches the column, which is what happens when
 * somebody else archived, deleted or moved one of its tasks since this board was loaded. The board
 * is stale rather than wrong, so it is reloaded before the user tries again: retrying against the
 * same stale column would only be refused a second time.
 */
async function resyncAfterRejectedDrag(summary) {
  toast.add({
    severity: 'warn',
    summary,
    detail: "Le tableau a été modifié entre temps, il vient d'être rechargé.",
    life: 5000
  })

  try {
    await tasksStore.fetchTasks(bandSpaceId)
  } catch {
    toast.add({ severity: 'error', summary: 'Rechargement du tableau impossible', life: 5000 })
  }
}

async function handleReorder(status, visibleOrderedIds, movedTaskId) {
  try {
    await tasksStore.reorderTasks(bandSpaceId, status, visibleOrderedIds, movedTaskId)
  } catch {
    await resyncAfterRejectedDrag('Impossible de réordonner les tâches')
  }
}

async function handleStatusChange(taskId, newStatus, visibleIndex) {
  try {
    await tasksStore.moveTaskToColumn(bandSpaceId, taskId, newStatus, visibleIndex)
  } catch {
    await resyncAfterRejectedDrag('Impossible de déplacer la tâche')
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
