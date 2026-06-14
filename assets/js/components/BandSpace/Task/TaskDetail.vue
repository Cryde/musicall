<template>
  <Drawer v-model:visible="visibleModel" position="right" class="w-full md:w-[48rem]!">
    <template #header>
      <span class="text-base font-semibold">Détail de la tâche</span>
    </template>

    <div
      v-if="!task && tasksStore.isLoadingActiveTask"
      class="flex flex-col gap-5"
    >
      <Skeleton width="60%" height="1.5rem" />
      <div class="grid grid-cols-2 gap-3">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="2.5rem" borderRadius="0.375rem" />
      </div>
      <Skeleton width="100%" height="6rem" borderRadius="0.375rem" />
    </div>

    <div
      v-else-if="!task && tasksStore.activeTaskError"
      class="flex flex-col items-center justify-center gap-4 py-10"
    >
      <Message severity="error" :closable="false">{{ tasksStore.activeTaskError }}</Message>
      <Button label="Fermer" severity="secondary" text @click="visibleModel = false" />
    </div>

    <div v-else-if="task" class="flex flex-col gap-5">
      <!-- Title (inline edit, save on Enter or via Save button) -->
      <div>
        <input
          v-model="editTitle"
          class="w-full text-lg font-semibold bg-transparent border-none outline-none text-surface-800 dark:text-surface-100 focus:ring-1 focus:ring-primary rounded px-1 -mx-1"
          @keydown.enter.prevent="saveTextFields"
        />
      </div>

      <!-- Metadata grid -->
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">Statut</label>
          <Select
            v-model="editStatus"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            size="small"
            aria-label="Statut"
            @change="saveField('status', editStatus)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">Priorité</label>
          <Select
            v-model="editPriority"
            :options="priorityOptions"
            optionLabel="label"
            optionValue="value"
            size="small"
            aria-label="Priorité"
            @change="saveField('priority', editPriority)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">Catégorie</label>
          <Select
            v-model="editCategoryId"
            :options="categories"
            optionLabel="name"
            optionValue="id"
            placeholder="Aucune"
            showClear
            size="small"
            aria-label="Catégorie"
            @change="saveField('category_id', editCategoryId)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">Échéance</label>
          <DatePicker
            v-model="editDueDate"
            dateFormat="yy-mm-dd"
            showIcon
            showButtonBar
            size="small"
            aria-label="Échéance"
            @date-select="saveDueDate"
            @clear-click="saveDueDate"
          />
        </div>
      </div>

      <!-- Assignees -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-surface-500">Assignés</label>
        <MultiSelect
          v-model="editAssigneeIds"
          :options="members"
          optionLabel="username"
          optionValue="user_id"
          placeholder="Sélectionner des membres"
          display="chip"
          size="small"
          aria-label="Assignés"
          @change="saveAssignees"
        />
      </div>

      <!-- Description (explicit save) -->
      <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between">
          <label class="text-xs font-medium text-surface-500">Description</label>
          <span
            v-if="hasTextChanges"
            class="text-xs text-amber-600 dark:text-amber-400"
          >
            Modifications non enregistrées
          </span>
        </div>
        <Textarea
          v-model="editDescription"
          rows="4"
          autoResize
          placeholder="Ajouter une description..."
          class="text-sm"
          aria-label="Description"
        />
        <div class="flex justify-end gap-2">
          <Button
            v-if="hasTextChanges"
            label="Annuler"
            severity="secondary"
            text
            size="small"
            @click="resetTextFields"
          />
          <Button
            label="Enregistrer"
            size="small"
            icon="pi pi-check"
            :disabled="!hasTextChanges"
            :loading="isSavingText"
            @click="saveTextFields"
          />
        </div>
      </div>

      <!-- Separator -->
      <div class="border-t border-surface-200 dark:border-surface-700"></div>

      <!-- Attached files -->
      <AttachedFilesSection
        v-if="task.id"
        :band-space-id="bandSpaceId"
        source-type="task"
        :source-id="task.id"
        @attached="tasksStore.bumpFileCount(task.id, 1)"
        @detached="tasksStore.bumpFileCount(task.id, -1)"
      />

      <!-- Separator -->
      <div class="border-t border-surface-200 dark:border-surface-700"></div>

      <!-- Comments -->
      <TaskCommentForm
        :members="members"
        :is-submitting="isSubmittingComment"
        @submit="handleCommentSubmit"
      />
      <TaskCommentList
        :comments="comments"
        :members="members"
        @edit="handleCommentEdit"
        @delete="handleCommentDelete"
      />

      <!-- Activity -->
      <TaskActivityFeed :activities="activities" />

      <!-- Archive / Unarchive / Delete -->
      <div class="border-t border-surface-200 dark:border-surface-700 pt-4 flex flex-col gap-2">
        <Button
          v-if="task.status === 'done' && !task.archive_datetime"
          label="Archiver"
          severity="secondary"
          text
          size="small"
          icon="pi pi-box"
          @click="handleArchive"
        />
        <Button
          v-if="task.archive_datetime"
          label="Désarchiver"
          severity="secondary"
          text
          size="small"
          icon="pi pi-replay"
          @click="handleUnarchive"
        />
        <Button
          label="Supprimer la tâche"
          severity="danger"
          text
          size="small"
          icon="pi pi-trash"
          :loading="tasksStore.isDeleting"
          @click="handleDelete"
        />
      </div>
    </div>
  </Drawer>
</template>

<script setup>
import { format } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import AttachedFilesSection from '../Files/AttachedFilesSection.vue'
import TaskActivityFeed from './TaskActivityFeed.vue'
import TaskCommentForm from './TaskCommentForm.vue'
import TaskCommentList from './TaskCommentList.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  taskId: { type: String, default: null },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['update:visible', 'deleted'])
const confirm = useConfirm()
const toast = useToast()
const tasksStore = useBandTasksStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const task = computed(() => tasksStore.activeTask)
const categories = computed(() => tasksStore.categories)
const members = computed(() => tasksStore.members)

const comments = ref([])
const activities = ref([])
const isSubmittingComment = ref(false)

// Edit state
const editTitle = ref('')
const editStatus = ref('todo')
const editPriority = ref('normal')
const editCategoryId = ref(null)
const editDueDate = ref(null)
const editAssigneeIds = ref([])
const editDescription = ref('')

const statusOptions = [
  { label: 'À faire', value: 'todo' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminé', value: 'done' }
]

const priorityOptions = [
  { label: 'Normal', value: 'normal' },
  { label: 'Haute', value: 'high' },
  { label: 'Urgent', value: 'urgent' }
]

// Track which task id we've loaded comments/activities for, so we don't
// refetch on every task update (e.g. after saveField).
const detailsLoadedFor = ref(null)

watch(
  () => props.visible,
  (val) => {
    if (!val) {
      comments.value = []
      activities.value = []
      detailsLoadedFor.value = null
    }
  }
)

// Tracks the id of the task currently reflected in the edit refs, so we can
// distinguish "first load / switched task" (populate everything) from
// "same task updated via auto-save" (preserve in-progress text edits).
const lastPopulatedId = ref(null)

watch(task, async () => {
  if (!task.value) return
  if (props.visible && detailsLoadedFor.value !== task.value.id) {
    detailsLoadedFor.value = task.value.id
    await loadDetails()
  }
  if (lastPopulatedId.value !== task.value.id) {
    populateForm()
    return
  }
  if (hasTextChanges.value) {
    editStatus.value = task.value.status
    editPriority.value = task.value.priority
    editCategoryId.value = task.value.category_id
    editDueDate.value = task.value.due_date ? new Date(task.value.due_date) : null
    editAssigneeIds.value = task.value.assignees.map((a) => a.id)
    return
  }
  populateForm()
})

function populateForm() {
  if (!task.value) return
  editTitle.value = task.value.title
  editStatus.value = task.value.status
  editPriority.value = task.value.priority
  editCategoryId.value = task.value.category_id
  editDueDate.value = task.value.due_date ? new Date(task.value.due_date) : null
  editAssigneeIds.value = task.value.assignees.map((a) => a.id)
  editDescription.value = task.value.description || ''
  lastPopulatedId.value = task.value.id
}

async function loadDetails() {
  try {
    const [c, a] = await Promise.all([
      bandSpaceTasksApi.getComments(props.bandSpaceId, props.taskId),
      bandSpaceTasksApi.getActivities(props.bandSpaceId, props.taskId)
    ])
    comments.value = c
    activities.value = a
  } catch {
    toast.add({
      severity: 'warn',
      summary: 'Impossible de charger les commentaires et activités',
      life: 5000
    })
  }
}

async function saveField(field, value) {
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, { [field]: value })
    toast.add({ severity: 'success', summary: 'Modifications enregistrées', life: 2000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  }
}

const isSavingText = ref(false)

const hasTextChanges = computed(() => {
  if (!task.value) return false
  const trimmedTitle = editTitle.value.trim()
  const titleChanged = trimmedTitle !== '' && trimmedTitle !== task.value.title
  const descriptionChanged = editDescription.value !== (task.value.description || '')
  return titleChanged || descriptionChanged
})

function resetTextFields() {
  if (!task.value) return
  editTitle.value = task.value.title
  editDescription.value = task.value.description || ''
}

async function saveTextFields() {
  if (!task.value) return

  const trimmedTitle = editTitle.value.trim()
  if (!trimmedTitle) {
    toast.add({ severity: 'error', summary: 'Le titre ne peut pas être vide', life: 5000 })
    editTitle.value = task.value.title
    return
  }

  const payload = {}
  if (trimmedTitle !== task.value.title) payload.title = trimmedTitle
  if (editDescription.value !== (task.value.description || '')) {
    payload.description = editDescription.value || null
  }
  if (Object.keys(payload).length === 0) return

  isSavingText.value = true
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, payload)
    toast.add({ severity: 'success', summary: 'Modifications enregistrées', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  } finally {
    isSavingText.value = false
  }
}

async function saveDueDate() {
  const value = editDueDate.value ? format(editDueDate.value, 'yyyy-MM-dd') : null
  await saveField('due_date', value)
}

async function saveAssignees() {
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, {
      assignee_ids: editAssigneeIds.value
    })
    toast.add({ severity: 'success', summary: 'Modifications enregistrées', life: 2000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  }
}

async function handleCommentSubmit(content) {
  isSubmittingComment.value = true
  try {
    await tasksStore.createComment(props.bandSpaceId, props.taskId, { content })
    await loadDetails()
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  } finally {
    isSubmittingComment.value = false
  }
}

async function handleCommentEdit(commentId, content) {
  try {
    await tasksStore.updateComment(props.bandSpaceId, props.taskId, commentId, { content })
    await loadDetails()
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleCommentDelete(commentId) {
  try {
    await tasksStore.deleteComment(props.bandSpaceId, props.taskId, commentId)
    await loadDetails()
    toast.add({ severity: 'success', summary: 'Commentaire supprimé', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleArchive() {
  try {
    await tasksStore.archiveTask(props.bandSpaceId, props.taskId)
    visibleModel.value = false
    toast.add({ severity: 'success', summary: 'Tâche archivée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleUnarchive() {
  try {
    await tasksStore.unarchiveTask(props.bandSpaceId, props.taskId)
    visibleModel.value = false
    toast.add({ severity: 'success', summary: 'Tâche désarchivée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function handleDelete() {
  confirm.require({
    message: 'Es-tu sûr de vouloir supprimer cette tâche ?',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await tasksStore.deleteTask(props.bandSpaceId, props.taskId)
        emit('deleted')
        visibleModel.value = false
        toast.add({ severity: 'success', summary: 'Tâche supprimée', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}
</script>
