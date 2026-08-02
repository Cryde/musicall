<template>
  <div class="flex flex-col gap-3">
    <div
      v-for="(item, index) in items"
      :key="item.id"
      class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden"
      :class="[
        dropTargetId === item.id ? 'ring-2 ring-primary-400' : '',
        // Dimmed, never hidden: the point of the list is to see at a glance what the
        // document will contain, which includes seeing what it will leave out.
        item.is_included ? '' : 'opacity-60'
      ]"
      @dragover.prevent="handleDragOver(item.id)"
      @dragleave="handleDragLeave(item.id)"
      @drop.prevent="handleDrop(item.id)"
    >
      <div
        class="flex items-center gap-2 px-3 py-2 bg-surface-50 dark:bg-surface-800"
        :draggable="!readOnly"
        @dragstart="handleDragStart(item.id)"
        @dragend="handleDragEnd"
      >
        <i
          v-if="!readOnly"
          class="pi pi-bars text-surface-400 cursor-grab shrink-0"
          aria-hidden="true"
        />

        <Button
          :icon="isCollapsed(item.id) ? 'pi pi-chevron-right' : 'pi pi-chevron-down'"
          severity="secondary"
          text
          rounded
          size="small"
          :aria-expanded="!isCollapsed(item.id)"
          :aria-label="`${isCollapsed(item.id) ? 'Déplier' : 'Replier'} l'élément ${item.title}`"
          @click="toggleCollapsed(item.id)"
        />

        <i :class="['pi', typeIcon(item.type), 'text-surface-500 shrink-0']" aria-hidden="true" />
        <h3 class="font-semibold min-w-0 truncate">{{ item.title }}</h3>
        <span
          v-if="!item.is_included"
          class="text-xs px-2 py-0.5 rounded bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-200 shrink-0"
        >
          Exclu
        </span>
        <span class="flex-1" />

        <div v-if="!readOnly" class="flex items-center gap-1 shrink-0">
          <ToggleSwitch
            :model-value="item.is_included"
            :aria-label="`Inclure « ${item.title} » dans le document`"
            v-tooltip.top="item.is_included ? 'Retirer du document' : 'Inclure dans le document'"
            @update:model-value="(value) => setIncluded(item, value)"
          />
          <!-- Drag is not an accessible reorder, so the same move is available as buttons. -->
          <Button
            icon="pi pi-arrow-up"
            severity="secondary"
            text
            rounded
            size="small"
            :disabled="index === 0"
            :aria-label="`Monter l'élément ${item.title}`"
            v-tooltip.top="'Monter'"
            @click="move(index, index - 1)"
          />
          <Button
            icon="pi pi-arrow-down"
            severity="secondary"
            text
            rounded
            size="small"
            :disabled="index === items.length - 1"
            :aria-label="`Descendre l'élément ${item.title}`"
            v-tooltip.top="'Descendre'"
            @click="move(index, index + 1)"
          />
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            size="small"
            :aria-label="`Renommer l'élément ${item.title}`"
            v-tooltip.top="'Renommer'"
            @click="openRename(item)"
          />
          <Button
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            :aria-label="`Supprimer l'élément ${item.title}`"
            v-tooltip.top="'Supprimer'"
            @click="confirmDelete(item)"
          />
        </div>

        <span class="text-xs text-surface-600 dark:text-surface-300 shrink-0 w-32 text-right" aria-live="polite">
          <template v-if="statusFor(item.id) === 'saving'">
            <i class="pi pi-spin pi-spinner mr-1" aria-hidden="true" />Sauvegarde...
          </template>
          <template v-else-if="statusFor(item.id) === 'saved'">
            <i class="pi pi-check mr-1 text-green-600 dark:text-green-400" aria-hidden="true" />Sauvegardé
          </template>
          <template v-else-if="statusFor(item.id) === 'error'">
            <i class="pi pi-times mr-1 text-red-600 dark:text-red-400" aria-hidden="true" />
            {{ errorFor(item.id) }}
          </template>
        </span>
      </div>

      <div v-show="!isCollapsed(item.id)" class="p-3">
        <RiderTextItemEditor
          v-if="item.type === 'text'"
          :item-id="item.id"
          :title="item.title"
          :content="item.content"
          :read-only="readOnly"
          @save="handleSave"
        />
        <RiderDocumentItemEditor
          v-else-if="item.type === 'document'"
          :band-space-id="bandSpaceId"
          :item-id="item.id"
          :file="item.file"
          :read-only="readOnly"
          @choose="handleChooseFile"
        />
        <p v-else class="text-surface-600 dark:text-surface-300 py-4 text-center">
          Ce type d'élément arrivera prochainement.
        </p>
      </div>
    </div>

    <div v-if="!readOnly">
      <Button label="Ajouter un élément" icon="pi pi-plus" severity="secondary" outlined @click="addDialogOpen = true" />
    </div>

    <Dialog v-model:visible="addDialogOpen" modal header="Nouvel élément" :style="{ width: '26rem' }">
      <form class="flex flex-col gap-4" @submit.prevent="handleAdd">
        <div>
          <label for="newItemTitle" class="block text-sm font-medium mb-1">
            Titre <span class="text-red-600 dark:text-red-400">*</span>
          </label>
          <InputText id="newItemTitle" v-model="newTitle" autofocus class="w-full" placeholder="ex. Loges" />
        </div>
        <div>
          <label for="newItemType" class="block text-sm font-medium mb-1">Type</label>
          <Select
            id="newItemType"
            v-model="newType"
            :options="ITEM_TYPES"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" severity="secondary" text type="button" @click="addDialogOpen = false" />
          <Button label="Ajouter" type="submit" :loading="isAdding" :disabled="!newTitle.trim()" />
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="renameDialogOpen" modal header="Renommer l'élément" :style="{ width: '26rem' }">
      <form class="flex flex-col gap-4" @submit.prevent="handleRename">
        <div>
          <label for="renameItemTitle" class="block text-sm font-medium mb-1">
            Titre <span class="text-red-600 dark:text-red-400">*</span>
          </label>
          <InputText id="renameItemTitle" v-model="renameTitle" autofocus class="w-full" />
        </div>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" severity="secondary" text type="button" @click="renameDialogOpen = false" />
          <Button label="Renommer" type="submit" :loading="isRenaming" :disabled="!renameTitle.trim()" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { reactive, ref } from 'vue'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import RiderDocumentItemEditor from './RiderDocumentItemEditor.vue'
import RiderTextItemEditor from './RiderTextItemEditor.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  items: { type: Array, required: true },
  readOnly: { type: Boolean, default: false }
})

const techRidersStore = useBandTechRidersStore()
const confirm = useConfirm()
const toast = useToast()

// Per item, because items autosave independently and each needs its own answer.
const saveStatus = reactive({})
const saveError = reactive({})
const collapsed = reactive({})

const addDialogOpen = ref(false)
const ITEM_TYPES = [
  { value: 'text', label: 'Texte libre' },
  { value: 'document', label: 'Document (image ou PDF)' }
]

const newTitle = ref('')
const newType = ref('text')
const isAdding = ref(false)

const renameDialogOpen = ref(false)
const renameTitle = ref('')
const renameTarget = ref(null)
const isRenaming = ref(false)

const draggedId = ref(null)
const dropTargetId = ref(null)

const TYPE_ICONS = {
  text: 'pi-align-left',
  contacts: 'pi-users',
  stage_plot: 'pi-map',
  patch_list: 'pi-sliders-h',
  document: 'pi-file'
}

function typeIcon(type) {
  return TYPE_ICONS[type] ?? 'pi-align-left'
}

async function handleChooseFile({ itemId, fileId }) {
  try {
    await techRidersStore.setItemFile(props.bandSpaceId, props.riderId, itemId, fileId)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

async function setIncluded(item, isIncluded) {
  try {
    await techRidersStore.setItemIncluded(props.bandSpaceId, props.riderId, item.id, isIncluded)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

function statusFor(itemId) {
  return saveStatus[itemId] ?? null
}

function errorFor(itemId) {
  return saveError[itemId] ?? 'Erreur'
}

function isCollapsed(itemId) {
  return collapsed[itemId] === true
}

function toggleCollapsed(itemId) {
  collapsed[itemId] = !isCollapsed(itemId)
}

/**
 * An autosave failure is easy to miss, so the status shows the reason rather than merely
 * ceasing to say "Sauvegardé". The server's own message is used, which is how the content
 * size cap reaches the user.
 */
async function handleSave({ itemId, content }) {
  saveStatus[itemId] = 'saving'
  saveError[itemId] = null
  try {
    await techRidersStore.saveItemContent(props.bandSpaceId, props.riderId, itemId, content)
    saveStatus[itemId] = 'saved'
  } catch (e) {
    saveStatus[itemId] = 'error'
    saveError[itemId] = e.violationsByField?.content?.[0]?.message ?? e.message ?? 'Erreur'
  }
}

async function handleAdd() {
  const title = newTitle.value.trim()
  if (!title) return

  isAdding.value = true
  try {
    await techRidersStore.createItem(props.bandSpaceId, props.riderId, {
      title,
      type: newType.value
    })
    toast.add({ severity: 'success', summary: 'Élément ajouté', life: 2500 })
    addDialogOpen.value = false
    newTitle.value = ''
    newType.value = 'text'
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isAdding.value = false
  }
}

function openRename(item) {
  renameTarget.value = item
  renameTitle.value = item.title
  renameDialogOpen.value = true
}

async function handleRename() {
  const title = renameTitle.value.trim()
  if (!title || !renameTarget.value) return

  isRenaming.value = true
  try {
    await techRidersStore.renameItem(props.bandSpaceId, props.riderId, renameTarget.value.id, title)
    renameDialogOpen.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isRenaming.value = false
  }
}

function confirmDelete(item) {
  confirm.require({
    message: `« ${item.title} » et son contenu seront supprimés. Cette action est irréversible.`,
    header: "Supprimer l'élément",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await techRidersStore.deleteItem(props.bandSpaceId, props.riderId, item.id)
        toast.add({ severity: 'success', summary: 'Élément supprimé', life: 2500 })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}

async function move(fromIndex, toIndex) {
  if (toIndex < 0 || toIndex >= props.items.length) return

  const ids = props.items.map((item) => item.id)
  const [moved] = ids.splice(fromIndex, 1)
  ids.splice(toIndex, 0, moved)

  try {
    await techRidersStore.reorderItems(props.bandSpaceId, props.riderId, ids)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Réordonnancement impossible',
      detail: e.message,
      life: 5000
    })
  }
}

function handleDragStart(itemId) {
  if (props.readOnly) return
  draggedId.value = itemId
}

function handleDragOver(itemId) {
  if (!draggedId.value || draggedId.value === itemId) return
  dropTargetId.value = itemId
}

function handleDragLeave(itemId) {
  if (dropTargetId.value === itemId) {
    dropTargetId.value = null
  }
}

// dragend always fires on the source, so a drop outside the list cannot leave the row stuck
// in its dragging state. Same rule the Files module relies on.
function handleDragEnd() {
  draggedId.value = null
  dropTargetId.value = null
}

function handleDrop(targetId) {
  const sourceId = draggedId.value
  dropTargetId.value = null
  draggedId.value = null
  if (!sourceId || sourceId === targetId) return

  const fromIndex = props.items.findIndex((item) => item.id === sourceId)
  const toIndex = props.items.findIndex((item) => item.id === targetId)
  if (fromIndex === -1 || toIndex === -1) return

  move(fromIndex, toIndex)
}
</script>
