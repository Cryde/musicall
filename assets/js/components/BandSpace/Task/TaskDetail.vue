<template>
  <!-- `!`, or PrimeVue's own width wins and a phone gets a 20rem strip beside the board. -->
  <Drawer v-model:visible="visibleModel" position="right" class="w-full! md:w-[48rem]!" :show-close-icon="!isPhone" :close-on-escape="!isAnySheetOpen">
    <template #header>
      <!-- A phone gets the app's screen: back to the list on the left, the task's actions on the right. -->
      <PhoneBackBar
        v-if="isPhone"
        :back-label="t('band_space_tasks.detail.back')"
        :title="t('band_space_tasks.detail.title')"
        @back="visibleModel = false"
      >
        <template #actions>
          <Button
            v-if="hasTaskActions"
            icon="pi pi-ellipsis-v"
            text
            rounded
            severity="secondary"
            :aria-label="t('band_space_tasks.detail.more_actions')"
            aria-haspopup="dialog"
            :aria-expanded="isActionSheetOpen"
            @click="isActionSheetOpen = true"
          />
        </template>
      </PhoneBackBar>
      <span v-else class="text-base font-semibold">{{ t('band_space_tasks.detail.title') }}</span>
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
      <Button :label="t('band_space_tasks.detail.close')" severity="secondary" text @click="visibleModel = false" />
    </div>

    <div v-else-if="task" class="flex flex-col gap-5">
      <Message v-if="isArchived" severity="info" :closable="false">
        {{ t('band_space_tasks.detail.archived_notice') }}
      </Message>

      <!-- Where this task was said out loud (#979). The attachment row that puts a card under the
           message is read the other way round to get here, so no task carries a column for it. -->
      <RouterLink
        v-if="chatLink"
        :to="chatLink"
        class="inline-flex w-fit items-center gap-2 text-sm text-primary-700 hover:underline dark:text-primary-300"
      >
        <i class="pi pi-comments text-xs" aria-hidden="true" />
        {{ t('band_space_tasks.detail.see_in_chat') }}
      </RouterLink>

      <!-- Title (inline edit, save on Enter or via Save button) -->
      <div class="flex items-start gap-3">
        <button
          v-if="isPhone"
          type="button"
          class="-m-1 mt-0 shrink-0 rounded-lg p-1 focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-default"
          :aria-label="t('band_space_tasks.detail.change_status', { status: statusLabel })"
          aria-haspopup="dialog"
          :disabled="isArchived"
          @click="isStatusSheetOpen = true"
        >
          <TaskStatusMark :status="editStatus" />
        </button>
        <!-- Grows with the title, so a long one wraps on a phone instead of being cut; Enter still
             saves it, a title having no line break. -->
        <textarea
          ref="titleField"
          v-model="editTitle"
          rows="1"
          :readonly="isArchived"
          :aria-label="t('band_space_tasks.detail.title_aria')"
          class="block w-full resize-none overflow-hidden text-lg font-semibold bg-transparent border-none outline-none text-surface-800 dark:text-surface-100 focus:ring-1 focus:ring-primary rounded px-1 -mx-1 read-only:text-surface-500 dark:read-only:text-surface-400"
          @keydown.enter.prevent="saveTextFields"
        />
      </div>

      <TaskFieldRows
        v-if="isPhone"
        v-model:open-sheet="openFieldSheet"
        :priority="editPriority"
        :due-date="editDueDate"
        :assignee-ids="editAssigneeIds"
        :category-id="editCategoryId"
        :priority-options="priorityOptions"
        :categories="categories"
        :members="members"
        :disabled="isArchived"
        @change="handleFieldChange"
      >
        <template #priority>
          <span v-if="editPriority !== 'normal'" class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="PRIORITY_BADGE[editPriority]">
            {{ priorityLabel }}
          </span>
          <template v-else>{{ priorityLabel }}</template>
        </template>
        <template #due>{{ dueLabel }}</template>
        <template #assignees>
          <template v-if="assignedMembers.length === 0">{{ t('band_space_tasks.detail.no_assignee') }}</template>
          <template v-else>
            <span class="flex -space-x-1.5">
              <Avatar
                v-for="member in assignedMembers.slice(0, 3)"
                :key="member.user_id"
                :username="member.username"
                :display-name="member.display_name"
                :picture-url="member.profile_picture_url"
                size="sm"
              />
            </span>
            <span class="truncate">
              {{ assignedMembers.length === 1 ? assignedMembers[0].display_name : t('band_space_tasks.detail.assignee_count', assignedMembers.length) }}
            </span>
          </template>
        </template>
        <template #category>
          <span v-if="activeCategory" class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: activeCategory.color }" aria-hidden="true" />
          <span class="truncate">{{ activeCategory?.name ?? t('band_space_tasks.field.no_category') }}</span>
        </template>
      </TaskFieldRows>

      <!-- Metadata grid -->
      <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.status') }}</label>
          <Select
            v-model="editStatus"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            size="small"
            :aria-label="t('band_space_tasks.field.status')"
            :disabled="isArchived"
            @change="saveField('status', editStatus)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.priority') }}</label>
          <Select
            v-model="editPriority"
            :options="priorityOptions"
            optionLabel="label"
            optionValue="value"
            size="small"
            :aria-label="t('band_space_tasks.field.priority')"
            :disabled="isArchived"
            @change="saveField('priority', editPriority)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.category') }}</label>
          <Select
            v-model="editCategoryId"
            :options="categories"
            optionLabel="name"
            optionValue="id"
            :placeholder="t('band_space_tasks.field.no_category')"
            showClear
            size="small"
            :aria-label="t('band_space_tasks.field.category')"
            :disabled="isArchived"
            @change="saveField('category_id', editCategoryId)"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.due') }}</label>
          <DatePicker
            v-model="editDueDate"
            showIcon
            showButtonBar
            size="small"
            :aria-label="t('band_space_tasks.field.due')"
            :disabled="isArchived"
            @date-select="saveDueDate"
            @clear-click="saveDueDate"
          />
        </div>
      </div>

      <!-- Assignees -->
      <div v-if="!isPhone" class="flex flex-col gap-1">
        <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.assignees') }}</label>
        <MultiSelect
          v-model="editAssigneeIds"
          :options="members"
          optionLabel="display_name"
          optionValue="user_id"
          :placeholder="t('band_space_tasks.field.select_members')"
          display="chip"
          size="small"
          :aria-label="t('band_space_tasks.field.assignees')"
          :disabled="isArchived"
          @change="saveAssignees"
        />
      </div>

      <!-- Description (explicit save) -->
      <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between">
          <label class="text-xs font-medium text-surface-500">{{ t('band_space_tasks.field.description') }}</label>
          <span
            v-if="hasTextChanges"
            class="text-xs text-amber-600 dark:text-amber-400"
          >
            {{ t('band_space_tasks.detail.unsaved_changes') }}
          </span>
        </div>
        <Textarea
          v-model="editDescription"
          rows="4"
          autoResize
          :placeholder="
            isArchived
              ? t('band_space_tasks.detail.no_description')
              : t('band_space_tasks.detail.add_description')
          "
          class="text-sm"
          :aria-label="t('band_space_tasks.field.description')"
          :disabled="isArchived"
        />
        <Message v-if="textConflict && hasTextChanges" severity="warn" :closable="false">
          {{ textConflict }} {{ t('band_space_tasks.detail.conflict_kept') }}
        </Message>
        <div v-if="!isArchived" class="flex justify-end gap-2">
          <Button
            v-if="hasTextChanges"
            :label="t('band_space_tasks.common.cancel')"
            severity="secondary"
            text
            size="small"
            @click="resetTextFields"
          />
          <Button
            :label="t('band_space_tasks.common.save')"
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
        :can-attach="!isArchived"
        @attached="tasksStore.bumpFileCount(task.id, 1)"
        @detached="tasksStore.bumpFileCount(task.id, -1)"
      />

      <!-- Separator -->
      <div class="border-t border-surface-200 dark:border-surface-700"></div>

      <!-- Comments -->
      <TaskCommentForm
        v-if="!isArchived"
        :members="members"
        :submit-comment="handleCommentSubmit"
        :is-submitting="isSubmittingComment"
      />
      <h4
        v-else
        class="text-sm font-semibold text-surface-700 dark:text-surface-200"
      >
        {{ t('band_space_tasks.common.comments') }}
      </h4>
      <TaskCommentList
        :comments="comments"
        :members="members"
        :read-only="isArchived"
        :save-comment="handleCommentEdit"
        @delete="handleCommentDelete"
      />

      <!-- Activity -->
      <TaskActivityFeed :activities="activities" />

      <!-- Archive / Unarchive / Delete: behind « ⋮ » on a phone -->
      <div
        v-if="hasTaskActions && !isPhone"
        class="border-t border-surface-200 dark:border-surface-700 pt-4 flex flex-col gap-2"
      >
        <Button
          v-if="canArchive"
          :label="t('band_space_tasks.common.archive')"
          severity="secondary"
          text
          size="small"
          icon="pi pi-box"
          @click="handleArchive"
        />
        <Button
          v-if="isArchived"
          :label="t('band_space_tasks.common.unarchive')"
          severity="secondary"
          text
          size="small"
          icon="pi pi-replay"
          @click="handleUnarchive"
        />
        <Button
          v-if="canDelete"
          :label="t('band_space_tasks.detail.delete')"
          severity="danger"
          text
          size="small"
          icon="pi pi-trash"
          :loading="tasksStore.isDeleting"
          @click="handleDelete"
        />
      </div>
    </div>

    <TaskStatusSheet v-if="isPhone" v-model:visible="isStatusSheetOpen" :task="task" @choose="handleFieldChange('status', $event)" />

    <BottomSheet v-if="isPhone" v-model:visible="isActionSheetOpen" :title="t('band_space_tasks.detail.more_actions')">
      <SheetAction v-if="canArchive" icon="pi-box" @click="runTaskAction(handleArchive)">{{ t('band_space_tasks.common.archive') }}</SheetAction>
      <SheetAction v-if="isArchived" icon="pi-replay" @click="runTaskAction(handleUnarchive)">{{ t('band_space_tasks.common.unarchive') }}</SheetAction>
      <SheetAction v-if="canDelete" icon="pi-trash" danger @click="runTaskAction(handleDelete)">{{ t('band_space_tasks.detail.delete') }}</SheetAction>
    </BottomSheet>
  </Drawer>
</template>

<script setup>
import { useTextareaAutosize } from '@vueuse/core'
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
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandSpaceLiveRefresh } from '../../../composables/useBandSpaceLiveRefresh.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { intlLocale } from '../../../i18n/formatLocale.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { attachedFilesNotice } from '../../../utils/attachedFilesNotice.js'
import { recordOpenedItem } from '../../../utils/openedItemRecorder.js'
import { canDeleteTask } from '../../../utils/taskActions.js'
import { singleLineTitle } from '../../../utils/taskTitle.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import PhoneBackBar from '../../Global/PhoneBackBar.vue'
import SheetAction from '../../Global/SheetAction.vue'
import Avatar from '../../User/Avatar.vue'
import AttachedFilesSection from '../Files/AttachedFilesSection.vue'
import TaskActivityFeed from './TaskActivityFeed.vue'
import TaskCommentForm from './TaskCommentForm.vue'
import TaskCommentList from './TaskCommentList.vue'
import TaskFieldRows from './TaskFieldRows.vue'
import TaskStatusMark from './TaskStatusMark.vue'
import TaskStatusSheet from './TaskStatusSheet.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  taskId: { type: String, default: null },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['update:visible', 'deleted'])
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
const tasksStore = useBandTasksStore()
const userSecurityStore = useUserSecurityStore()
const { isAdmin } = useBandSpaceNavigation()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const task = computed(() => tasksStore.activeTask)
const categories = computed(() => tasksStore.categories)
const members = computed(() => tasksStore.members)

/**
 * An archived task is a closed record and the API refuses every write to it, so the drawer opens
 * read-only rather than offering fields that come back with a refusal. Unarchiving and deleting
 * stay: neither edits the record, and taking the task back out is how it becomes editable again.
 */
const isArchived = computed(() => Boolean(task.value?.archive_datetime))

const canArchive = computed(() => task.value?.status === 'done' && !isArchived.value)

// Only the creator and an administrator get past TaskDeleteProcessor, so only they are offered the
// button. Everyone else used to confirm a destructive dialog and read the refusal afterwards.
const canDelete = computed(() =>
  canDeleteTask(task.value, userSecurityStore.userProfile?.id ?? null, isAdmin.value)
)

const chatLink = computed(() => {
  if (!task.value?.linked_message_id) {
    return null
  }

  return {
    name: BAND_SPACE_ROUTES.CHAT,
    params: { id: props.bandSpaceId },
    query: { message: task.value.linked_message_id }
  }
})

const comments = ref([])
const activities = ref([])
const isSubmittingComment = ref(false)

// Edit state
const editTitle = ref('')
useTextareaAutosize({ element: useTemplateRef('titleField'), input: editTitle })
const editStatus = ref('todo')
const editPriority = ref('normal')
const editCategoryId = ref(null)
const editDueDate = ref(null)
const editAssigneeIds = ref([])
const editDescription = ref('')

const statusOptions = computed(() => [
  { label: t('band_space_tasks.status.todo'), value: 'todo' },
  { label: t('band_space_tasks.status.in_progress'), value: 'in_progress' },
  { label: t('band_space_tasks.status.done'), value: 'done' }
])

const priorityOptions = computed(() => [
  { label: t('band_space_tasks.priority.normal'), value: 'normal' },
  { label: t('band_space_tasks.priority.high'), value: 'high' },
  { label: t('band_space_tasks.priority.urgent'), value: 'urgent' }
])

// ---------- A phone: the app's task screen ----------

// The board's own breakpoint: below it the drawer is the whole screen.
const isPhone = useIsBelowMd()
const isStatusSheetOpen = ref(false)
const isActionSheetOpen = ref(false)
const openFieldSheet = ref(null)
// Every open drawer closes itself on Escape, so the task's waits while one of its sheets is open.
const isAnySheetOpen = computed(
  () => isStatusSheetOpen.value || isActionSheetOpen.value || openFieldSheet.value !== null
)

const statusLabel = computed(
  () => statusOptions.value.find((option) => option.value === editStatus.value)?.label ?? ''
)

const hasTaskActions = computed(() => canArchive.value || isArchived.value || canDelete.value)

// The badges the board's cards show.
const PRIORITY_BADGE = {
  high: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
  urgent: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
}

const priorityLabel = computed(
  () => priorityOptions.value.find((option) => option.value === editPriority.value)?.label ?? ''
)

const dueLabel = computed(() =>
  editDueDate.value
    ? editDueDate.value.toLocaleDateString(intlLocale(), {
        weekday: 'short',
        day: 'numeric',
        month: 'short'
      })
    : t('band_space_tasks.detail.no_due')
)

const assignedMembers = computed(() =>
  members.value.filter((member) => editAssigneeIds.value.includes(member.user_id))
)

const activeCategory = computed(
  () => categories.value.find((category) => category.id === editCategoryId.value) ?? null
)

// A choice made in one of the phone's sheets, saved the way the desktop field it stands for saves it.
const FIELD_SAVERS = {
  status: (value) => {
    editStatus.value = value
    saveField('status', value)
  },
  priority: (value) => {
    editPriority.value = value
    saveField('priority', value)
  },
  category: (value) => {
    editCategoryId.value = value
    saveField('category_id', value)
  },
  due: (value) => {
    editDueDate.value = value
    saveDueDate()
  },
  assignees: (value) => {
    editAssigneeIds.value = value
    saveAssignees()
  }
}

function handleFieldChange(field, value) {
  FIELD_SAVERS[field](value)
}

function runTaskAction(action) {
  isActionSheetOpen.value = false
  action()
}

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
    // Once per open, and only once the task has loaded, so an id that no longer exists is not recorded (#1047).
    recordOpenedItem(props.bandSpaceId, 'task', task.value.id)
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
    syncAssigneeIds()
    takeServerTextWhereUntouched()
    return
  }
  populateForm()
})

/**
 * The picker only offers the current roster, so an assignment left over from a member who is no
 * longer in the band renders as no chip at all while staying in the model, and saving any other
 * change writes it straight back. Dropping it here means the next edit of the task clears it.
 */
// Each tap in the phone's sheet (and each pick in the multi select) saves at once, so a quick second
// one can still be on its way when the first answer lands: until every save has answered, the ticks on
// screen are the newest choice rather than the first answer.
let assigneeSavesInFlight = 0

function syncAssigneeIds() {
  if (assigneeSavesInFlight === 0) editAssigneeIds.value = assigneeIdsStillInTheBand()
}

function assigneeIdsStillInTheBand() {
  const assignedIds = task.value.assignees.map((a) => a.id)
  // An empty roster means it has not loaded, never that the band is empty: reading it the other way
  // would have the next save wipe every assignee off the task.
  if (members.value.length === 0) return assignedIds

  const rosterIds = new Set(members.value.map((m) => m.user_id))
  return assignedIds.filter((id) => rosterIds.has(id))
}

function populateForm() {
  if (!task.value) return
  editTitle.value = task.value.title
  editStatus.value = task.value.status
  editPriority.value = task.value.priority
  editCategoryId.value = task.value.category_id
  editDueDate.value = task.value.due_date ? new Date(task.value.due_date) : null
  syncAssigneeIds()
  editDescription.value = task.value.description || ''
  lastPopulatedId.value = task.value.id
  textBase.value = textBaseOf(task.value)
  textConflict.value = null
}

// The task itself comes back through the board (Tasks.vue), and the watch above keeps a title or a
// description being typed. Comments and activities are this drawer's own.
useBandSpaceLiveRefresh({
  bandSpaceId: () => props.bandSpaceId,
  modules: ['task'],
  refresh: () => {
    if (props.visible && props.taskId) loadDetails({ quiet: true })
  }
})

/** `quiet`: a background refetch, which fails without a toast and keeps what is on screen. */
async function loadDetails({ quiet = false } = {}) {
  const taskId = props.taskId
  try {
    const [c, a] = await Promise.all([
      bandSpaceTasksApi.getComments(props.bandSpaceId, taskId),
      bandSpaceTasksApi.getActivities(props.bandSpaceId, taskId)
    ])
    // Another task opened while this one was loading.
    if (taskId !== props.taskId) return
    comments.value = c
    activities.value = a
  } catch {
    if (quiet) return
    toast.add({
      severity: 'warn',
      summary: t('band_space_tasks.detail.load_failed'),
      life: 5000
    })
  }
}

async function saveField(field, value) {
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, { [field]: value })
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.common.changes_saved'),
      life: 2000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  }
}

const isSavingText = ref(false)
// What the title and description fields were filled from, and at which revision. Not read off the
// task: a live refetch replaces it under a member still typing (#1157), so a field nobody touched
// would read as edited, and a save would always carry the newest revision and erase what another
// member wrote meanwhile.
// One revision covers both fields, so a title changed by someone else while this member edits the
// description still refuses their save once: the banner, then a second save, is the way through.
const textBase = ref({ title: '', description: '', version: null })
const textConflict = ref(null)

function textBaseOf(source) {
  return {
    title: source.title,
    description: source.description || '',
    version: source.text_version
  }
}

/** Another member's title or description lands in whichever of the two fields is not being edited. */
function takeServerTextWhereUntouched() {
  if (editTitle.value === textBase.value.title) {
    editTitle.value = task.value.title
    textBase.value = { ...textBase.value, title: task.value.title }
  }
  if (editDescription.value === textBase.value.description) {
    editDescription.value = task.value.description || ''
    textBase.value = { ...textBase.value, description: task.value.description || '' }
  }
}

const hasTextChanges = computed(() => {
  if (!task.value) return false
  const trimmedTitle = singleLineTitle(editTitle.value)
  const titleChanged = trimmedTitle !== '' && trimmedTitle !== textBase.value.title
  const descriptionChanged = editDescription.value !== textBase.value.description
  return titleChanged || descriptionChanged
})

function resetTextFields() {
  if (!task.value) return
  editTitle.value = task.value.title
  editDescription.value = task.value.description || ''
  textBase.value = textBaseOf(task.value)
  textConflict.value = null
}

async function saveTextFields() {
  if (!task.value) return

  const trimmedTitle = singleLineTitle(editTitle.value)
  if (!trimmedTitle) {
    toast.add({ severity: 'error', summary: t('band_space_tasks.detail.title_empty'), life: 5000 })
    editTitle.value = task.value.title
    return
  }

  const payload = {}
  if (trimmedTitle !== textBase.value.title) payload.title = trimmedTitle
  if (editDescription.value !== textBase.value.description) {
    payload.description = editDescription.value || null
  }
  if (Object.keys(payload).length === 0) return
  payload.expected_text_version = textBase.value.version

  isSavingText.value = true
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, payload)
    // The fields now hold the saved revision, so they are its new base.
    resetTextFields()
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.common.changes_saved'),
      life: 3000
    })
  } catch (e) {
    if (e.status === 409) {
      await keepTextAfterConflict(e.message)
      return
    }
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
    populateForm()
  } finally {
    isSavingText.value = false
  }
}

/**
 * Somebody else changed the title or the description since the fields were filled. What the member
 * typed stays in the fields, the task underneath becomes the other member's version (the watch on
 * `task` keeps typed text), and saving again is now a choice made knowingly: it replaces theirs.
 */
async function keepTextAfterConflict(message) {
  textConflict.value = message
  const reread = await tasksStore.fetchTaskById(props.bandSpaceId, props.taskId, { quiet: true })
  // Unread, the base keeps its old revision, so saving again is refused again rather than blind.
  if (reread && task.value) textBase.value = { ...textBase.value, version: task.value.text_version }
}

async function saveDueDate() {
  const value = editDueDate.value ? format(editDueDate.value, 'yyyy-MM-dd') : null
  await saveField('due_date', value)
}

async function saveAssignees() {
  assigneeSavesInFlight++
  let failure = null
  try {
    await tasksStore.updateTask(props.bandSpaceId, props.taskId, {
      assignee_ids: editAssigneeIds.value
    })
  } catch (e) {
    failure = e
  } finally {
    assigneeSavesInFlight--
  }

  if (!failure) {
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.common.changes_saved'),
      life: 2000
    })
    return
  }
  toast.add({ severity: 'error', summary: failure.message, life: 5000 })
  // Put back what the server holds once nothing newer is on its way, or a later tap would be undone.
  if (assigneeSavesInFlight === 0) populateForm()
}

/**
 * These two let the failure through rather than swallowing it into a toast: the box that holds the
 * text is the one that has to decide whether to keep it, and it shows the message where the text is.
 */
async function handleCommentSubmit(content) {
  isSubmittingComment.value = true
  try {
    await tasksStore.createComment(props.bandSpaceId, props.taskId, { content })
    await loadDetails()
  } finally {
    isSubmittingComment.value = false
  }
}

async function handleCommentEdit(commentId, content) {
  await tasksStore.updateComment(props.bandSpaceId, props.taskId, commentId, { content })
  await loadDetails()
}

async function handleCommentDelete(commentId) {
  try {
    await tasksStore.deleteComment(props.bandSpaceId, props.taskId, commentId)
    await loadDetails()
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.detail.comment_deleted'),
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleArchive() {
  try {
    await tasksStore.archiveTask(props.bandSpaceId, props.taskId)
    visibleModel.value = false
    toast.add({ severity: 'success', summary: t('band_space_tasks.detail.archived'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleUnarchive() {
  try {
    await tasksStore.unarchiveTask(props.bandSpaceId, props.taskId)
    visibleModel.value = false
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.common.task_unarchived'),
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

function handleDelete() {
  confirm.require({
    message: `${t('band_space_tasks.detail.delete_confirm')}${attachedFilesNotice(task.value?.file_count)}`,
    header: t('band_space_tasks.common.confirm_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space_tasks.common.cancel'),
    acceptLabel: t('band_space_tasks.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await tasksStore.deleteTask(props.bandSpaceId, props.taskId)
        emit('deleted')
        visibleModel.value = false
        toast.add({
          severity: 'success',
          summary: t('band_space_tasks.detail.deleted'),
          life: 3000
        })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}
</script>
