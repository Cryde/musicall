<template>
  <Drawer v-model:visible="visibleModel" position="right" class="w-full md:w-[32rem]">
    <template #header>
      <span class="text-base font-semibold">Détail de la tâche</span>
    </template>

    <div v-if="task" class="flex flex-col gap-5">
      <!-- Title (inline edit) -->
      <div>
        <input
          v-model="editTitle"
          class="w-full text-lg font-semibold bg-transparent border-none outline-none text-surface-800 dark:text-surface-100 focus:ring-1 focus:ring-primary rounded px-1 -mx-1"
          @blur="saveTitle"
          @keydown.enter="$event.target.blur()"
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
          @change="saveAssignees"
        />
      </div>

      <!-- Description -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-surface-500">Description</label>
        <Textarea
          v-model="editDescription"
          rows="4"
          autoResize
          placeholder="Ajouter une description..."
          class="text-sm"
          @blur="saveDescription"
        />
      </div>

      <!-- Separator -->
      <div class="border-t border-surface-200 dark:border-surface-700"></div>

      <!-- Comments -->
      <TaskCommentForm
        :members="members"
        :is-submitting="isSubmittingComment"
        @submit="handleCommentSubmit"
      />
      <TaskCommentList :comments="comments" :members="members" />

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

    <ConfirmDialog />
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
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

watch(
  () => props.visible,
  async (val) => {
    if (val && props.taskId) {
      populateForm()
      await loadDetails()
    } else {
      comments.value = []
      activities.value = []
    }
  }
)

watch(task, () => {
  if (task.value) populateForm()
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
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  }
}

async function saveTitle() {
  const trimmed = editTitle.value.trim()
  if (!trimmed) {
    toast.add({ severity: 'error', summary: 'Le titre ne peut pas être vide', life: 5000 })
    editTitle.value = task.value?.title ?? ''
    return
  }
  if (trimmed === task.value?.title) return
  await saveField('title', trimmed)
}

async function saveDescription() {
  if (editDescription.value === (task.value?.description || '')) return
  await saveField('description', editDescription.value || null)
}

async function saveDueDate() {
  const value = editDueDate.value
    ? new Date(editDueDate.value).toISOString().split('T')[0]
    : null
  await saveField('due_date', value)
}

async function saveAssignees() {
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, {
      assignee_ids: editAssigneeIds.value
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  }
}

async function handleCommentSubmit(content) {
  isSubmittingComment.value = true
  try {
    await bandSpaceTasksApi.createComment(props.bandSpaceId, props.taskId, { content })
    await loadDetails()
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  } finally {
    isSubmittingComment.value = false
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
