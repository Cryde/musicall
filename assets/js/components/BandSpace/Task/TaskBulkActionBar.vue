<template>
  <div
    v-if="tasksStore.isSelectionMode && tasksStore.selectedTaskIds.size > 0"
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 bg-surface-900 dark:bg-surface-800 text-white rounded-xl shadow-xl px-4 py-2 flex items-center gap-3"
    :class="{ 'w-max max-w-[calc(100vw-1rem)] flex-wrap justify-center': isPhone }"
  >
    <span class="text-sm font-medium" :class="{ 'basis-full text-center': isPhone }">
      {{ t('band_space_tasks.bulk_bar.selected_count', tasksStore.selectedTaskIds.size) }}
    </span>

    <div v-if="!isPhone" class="w-px h-6 bg-surface-600"></div>

    <Button
      v-bind="actionName('band_space_tasks.field.status')"
      v-tooltip.top="isPhone ? t('band_space_tasks.field.status') : null"
      icon="pi pi-arrow-right"
      size="small"
      severity="secondary"
      :disabled="!!busy"
      @click="(e) => statusPopover.toggle(e)"
    />
    <Popover ref="statusPopover">
      <div class="flex flex-col gap-2 min-w-[16rem]">
        <Select
          v-model="statusDraft"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_tasks.bulk_bar.choose_status')"
        />
        <Button
          :label="t('band_space_tasks.bulk_bar.apply')"
          size="small"
          :loading="busy === 'status'"
          :disabled="statusDraft === null"
          @click="handleStatus"
        />
      </div>
    </Popover>

    <!-- The tooltip sits on the wrapper, since a disabled button fires no pointer event of its
         own, and a tooltip carries no accessible name either, hence the sr-only twin. -->
    <span v-tooltip.top="archiveBlockedReason">
      <Button
        v-bind="actionName('band_space_tasks.common.archive')"
        v-tooltip.top="isPhone ? t('band_space_tasks.common.archive') : null"
        icon="pi pi-box"
        size="small"
        severity="secondary"
        :loading="busy === 'archive'"
        :disabled="!!busy || archiveBlockers.length > 0"
        @click="handleArchive"
      />
      <span v-if="archiveBlockedReason" class="sr-only">{{ archiveBlockedReason }}</span>
    </span>

    <Button
      v-bind="actionName('band_space_tasks.field.category')"
      v-tooltip.top="isPhone ? t('band_space_tasks.field.category') : null"
      icon="pi pi-tag"
      size="small"
      severity="secondary"
      :disabled="!!busy"
      @click="(e) => categoryPopover.toggle(e)"
    />
    <Popover ref="categoryPopover">
      <div class="flex flex-col gap-2 min-w-[16rem]">
        <Select
          v-model="categoryDraft"
          :options="categoryOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_tasks.bulk_bar.choose_category')"
        />
        <Button
          :label="t('band_space_tasks.bulk_bar.apply')"
          size="small"
          :loading="busy === 'category'"
          @click="handleCategory"
        />
      </div>
    </Popover>

    <Button
      v-bind="actionName('band_space_tasks.field.assignees')"
      v-tooltip.top="isPhone ? t('band_space_tasks.field.assignees') : null"
      icon="pi pi-users"
      size="small"
      severity="secondary"
      :disabled="!!busy"
      @click="(e) => assigneePopover.toggle(e)"
    />
    <Popover ref="assigneePopover">
      <div class="flex flex-col gap-2 min-w-[18rem]">
        <MultiSelect
          v-model="assigneeDraft"
          :options="members"
          optionLabel="display_name"
          optionValue="user_id"
          :placeholder="t('band_space_tasks.bulk_bar.no_assignee')"
          display="chip"
        />
        <p class="text-xs text-surface-500">
          {{ t('band_space_tasks.bulk_bar.assignees_hint') }}
        </p>
        <Button
          :label="t('band_space_tasks.bulk_bar.apply')"
          size="small"
          :loading="busy === 'assignees'"
          @click="handleAssignees"
        />
      </div>
    </Popover>

    <span v-tooltip.top="deleteBlockedReason">
      <Button
        v-bind="actionName('band_space_tasks.common.delete')"
        v-tooltip.top="isPhone ? t('band_space_tasks.common.delete') : null"
        icon="pi pi-trash"
        size="small"
        severity="danger"
        :loading="busy === 'delete'"
        :disabled="!!busy || deleteBlockers.length > 0"
        @click="confirmDelete"
      />
      <span v-if="deleteBlockedReason" class="sr-only">{{ deleteBlockedReason }}</span>
    </span>

    <div v-if="!isPhone" class="w-px h-6 bg-surface-600"></div>

    <Button
      icon="pi pi-times"
      size="small"
      text
      severity="secondary"
      class="!text-white"
      v-tooltip.top="t('band_space_tasks.bulk_bar.exit_selection')"
      :aria-label="t('band_space_tasks.bulk_bar.exit_selection')"
      @click="tasksStore.exitSelectionMode"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiErrorDetail } from '../../../api/utils/apiErrorDetail.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { TASK_STATUSES } from '../../../constants/taskStatuses.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { attachedFilesNotice } from '../../../utils/attachedFilesNotice.js'
import { tasksBlockingArchive, tasksBlockingDelete } from '../../../utils/taskActions.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] }
})

const tasksStore = useBandTasksStore()
const userSecurityStore = useUserSecurityStore()
const { isAdmin } = useBandSpaceNavigation()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
// The phone tasks layout's breakpoint (Tasks.vue), where the bar is too narrow for its labels.
const isPhone = useIsBelowMd()

const busy = ref(null)
const statusDraft = ref(null)
const categoryDraft = ref(null)
const assigneeDraft = ref([])
const statusPopover = ref()
const categoryPopover = ref()
const assigneePopover = ref()

const statusOptions = computed(() =>
  TASK_STATUSES.map((status) => ({ label: t(status.labelKey), value: status.value }))
)

/**
 * On a phone the actions are icons, named by an aria-label and a tooltip. Switched here rather than
 * with a class, which PrimeVue's unlayered CSS would beat on its own button.
 */
function actionName(key) {
  return isPhone.value ? { 'aria-label': t(key) } : { label: t(key) }
}

const categoryOptions = computed(() => [
  { label: t('band_space_tasks.field.no_category'), value: null },
  ...props.categories.map((c) => ({ label: c.name, value: c.id }))
])

// Selection only happens on the board, so every ticked card is one of the loaded tasks.
const selectedTasks = computed(() =>
  tasksStore.tasks.filter((task) => tasksStore.selectedTaskIds.has(task.id))
)

const archiveBlockers = computed(() => tasksBlockingArchive(selectedTasks.value))

const deleteBlockers = computed(() =>
  tasksBlockingDelete(selectedTasks.value, userSecurityStore.userProfile?.id ?? null, isAdmin.value)
)

/**
 * The batch is one transaction, so a single card in the way takes the whole selection down. Rather
 * than let somebody run it and read a refusal, the button is greyed out and says which cards it is
 * waiting on. The names are the point: with eight cards ticked, "une tâche n'est pas terminée" is
 * not something a member can act on.
 */
const archiveBlockedReason = computed(() =>
  archiveBlockers.value.length === 0
    ? null
    : t('band_space_tasks.bulk_bar.archive_blocked', { tasks: archiveBlockers.value.join(', ') })
)

const deleteBlockedReason = computed(() =>
  deleteBlockers.value.length === 0
    ? null
    : t('band_space_tasks.bulk_bar.delete_blocked', { tasks: deleteBlockers.value.join(', ') })
)

async function runBulk(name, fn) {
  busy.value = name
  try {
    await fn()
  } catch (e) {
    // The refusal names the tasks that blocked the batch, which is the only part worth reading.
    toast.add({
      severity: 'error',
      summary: t('band_space_tasks.bulk_bar.failed'),
      detail: apiErrorDetail(e, t('band_space_tasks.bulk_bar.generic_error')),
      life: 8000
    })
  } finally {
    busy.value = null
  }
}

function handleArchive() {
  runBulk('archive', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { archived: true })
    toast.add({ severity: 'success', summary: t('band_space_tasks.bulk_bar.archived'), life: 3000 })
  })
}

function handleStatus() {
  statusPopover.value?.hide()
  runBulk('status', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { status: statusDraft.value })
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.bulk_bar.status_updated'),
      life: 3000
    })
    statusDraft.value = null
  })
}

function handleCategory() {
  categoryPopover.value?.hide()
  runBulk('category', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { category_id: categoryDraft.value })
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.bulk_bar.category_updated'),
      life: 3000
    })
    categoryDraft.value = null
  })
}

function handleAssignees() {
  assigneePopover.value?.hide()
  runBulk('assignees', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { assignee_ids: assigneeDraft.value })
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.bulk_bar.assignees_updated'),
      life: 3000
    })
    assigneeDraft.value = []
  })
}

function confirmDelete() {
  const count = tasksStore.selectedTaskIds.size
  const attachedFileCount = tasksStore.tasks
    .filter((task) => tasksStore.selectedTaskIds.has(task.id))
    .reduce((total, task) => total + (task.file_count ?? 0), 0)
  confirm.require({
    message: `${t('band_space_tasks.bulk_bar.delete_confirm', count)}${attachedFilesNotice(attachedFileCount)}`,
    header: t('band_space_tasks.common.confirm_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_tasks.common.delete'),
    rejectLabel: t('band_space_tasks.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: () => {
      runBulk('delete', async () => {
        await tasksStore.bulkDelete(props.bandSpaceId)
        toast.add({
          severity: 'success',
          summary: t('band_space_tasks.bulk_bar.deleted'),
          life: 3000
        })
      })
    }
  })
}
</script>
