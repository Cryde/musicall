<template>
  <div
    v-if="tasksStore.isSelectionMode && tasksStore.selectedTaskIds.size > 0"
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 bg-surface-900 dark:bg-surface-800 text-white rounded-xl shadow-xl px-4 py-2 flex items-center gap-3"
  >
    <span class="text-sm font-medium">
      {{ tasksStore.selectedTaskIds.size }}
      tâche{{ tasksStore.selectedTaskIds.size > 1 ? 's' : '' }} sélectionnée{{ tasksStore.selectedTaskIds.size > 1 ? 's' : '' }}
    </span>

    <div class="w-px h-6 bg-surface-600"></div>

    <!-- The tooltip sits on the wrapper, since a disabled button fires no pointer event of its
         own, and a tooltip carries no accessible name either, hence the sr-only twin. -->
    <span v-tooltip.top="archiveBlockedReason">
      <Button
        label="Archiver"
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
      label="Catégorie"
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
          placeholder="Choisir une catégorie"
        />
        <Button
          label="Appliquer"
          size="small"
          :loading="busy === 'category'"
          @click="handleCategory"
        />
      </div>
    </Popover>

    <Button
      label="Assignés"
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
          optionLabel="username"
          optionValue="user_id"
          placeholder="Aucun"
          display="chip"
        />
        <p class="text-xs text-surface-500">
          Remplace les assignés sur toutes les tâches sélectionnées.
        </p>
        <Button
          label="Appliquer"
          size="small"
          :loading="busy === 'assignees'"
          @click="handleAssignees"
        />
      </div>
    </Popover>

    <span v-tooltip.top="deleteBlockedReason">
      <Button
        label="Supprimer"
        icon="pi pi-trash"
        size="small"
        severity="danger"
        :loading="busy === 'delete'"
        :disabled="!!busy || deleteBlockers.length > 0"
        @click="confirmDelete"
      />
      <span v-if="deleteBlockedReason" class="sr-only">{{ deleteBlockedReason }}</span>
    </span>

    <div class="w-px h-6 bg-surface-600"></div>

    <Button
      icon="pi pi-times"
      size="small"
      text
      severity="secondary"
      class="!text-white"
      v-tooltip.top="'Annuler la sélection'"
      aria-label="Annuler la sélection"
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
import { apiErrorDetail } from '../../../api/utils/apiErrorDetail.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
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

const busy = ref(null)
const categoryDraft = ref(null)
const assigneeDraft = ref([])
const categoryPopover = ref()
const assigneePopover = ref()

const categoryOptions = computed(() => [
  { label: 'Aucune', value: null },
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
    : `Seules les tâches terminées peuvent être archivées : ${archiveBlockers.value.join(', ')}`
)

const deleteBlockedReason = computed(() =>
  deleteBlockers.value.length === 0
    ? null
    : `Seul le créateur ou un administrateur peut supprimer ces tâches : ${deleteBlockers.value.join(', ')}`
)

async function runBulk(name, fn) {
  busy.value = name
  try {
    await fn()
  } catch (e) {
    // The refusal names the tasks that blocked the batch, which is the only part worth reading.
    toast.add({
      severity: 'error',
      summary: 'Action en lot impossible',
      detail: apiErrorDetail(e, 'Une erreur est survenue'),
      life: 8000
    })
  } finally {
    busy.value = null
  }
}

function handleArchive() {
  runBulk('archive', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { archived: true })
    toast.add({ severity: 'success', summary: 'Tâches archivées', life: 3000 })
  })
}

function handleCategory() {
  categoryPopover.value?.hide()
  runBulk('category', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { category_id: categoryDraft.value })
    toast.add({ severity: 'success', summary: 'Catégorie mise à jour', life: 3000 })
    categoryDraft.value = null
  })
}

function handleAssignees() {
  assigneePopover.value?.hide()
  runBulk('assignees', async () => {
    await tasksStore.bulkPatch(props.bandSpaceId, { assignee_ids: assigneeDraft.value })
    toast.add({ severity: 'success', summary: 'Assignés mis à jour', life: 3000 })
    assigneeDraft.value = []
  })
}

function confirmDelete() {
  const count = tasksStore.selectedTaskIds.size
  const attachedFileCount = tasksStore.tasks
    .filter((task) => tasksStore.selectedTaskIds.has(task.id))
    .reduce((total, task) => total + (task.file_count ?? 0), 0)
  confirm.require({
    message: `Supprimer ${count} tâche${count > 1 ? 's' : ''} ? Cette action est irréversible.${attachedFilesNotice(attachedFileCount)}`,
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: () => {
      runBulk('delete', async () => {
        await tasksStore.bulkDelete(props.bandSpaceId)
        toast.add({ severity: 'success', summary: 'Tâches supprimées', life: 3000 })
      })
    }
  })
}
</script>
