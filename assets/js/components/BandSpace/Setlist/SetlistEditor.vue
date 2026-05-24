<template>
  <div>
    <div v-if="setlistsStore.isLoadingActive && !setlist" class="flex flex-col gap-3">
      <Skeleton width="60%" height="2.5rem" />
      <Skeleton v-for="i in 4" :key="i" width="100%" height="4rem" borderRadius="0.75rem" />
    </div>

    <div v-else-if="!setlist" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 border border-surface-200 dark:border-surface-700 text-center text-surface-500">
      Sélectionnez une setlist dans la barre latérale.
    </div>

    <div v-else class="flex flex-col gap-4">
      <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
        <div class="flex flex-wrap items-center gap-3 mb-3">
          <div class="flex-1 min-w-0">
            <InputText
              v-if="editingName"
              v-model="nameDraft"
              autofocus
              class="font-semibold text-xl w-full"
              @blur="commitRename"
              @keyup.enter="commitRename"
              @keyup.esc="cancelRename"
            />
            <h2
              v-else
              class="font-semibold text-xl truncate cursor-text hover:text-primary"
              v-tooltip.top="'Cliquez pour renommer'"
              @click="startRename"
            >
              {{ setlist.name }}
            </h2>
            <div class="text-xs text-surface-500 mt-1 flex items-center gap-3">
              <span>{{ setlist.items.length }} {{ setlist.items.length > 1 ? 'éléments' : 'élément' }}</span>
              <span>·&nbsp;Durée&nbsp;: <span class="tabular-nums">{{ formattedTotalDuration }}</span></span>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <Button label="Fichiers" icon="pi pi-folder" severity="secondary" size="small" @click="filesDrawerOpen = true" />
            <Button label="Exporter PDF" icon="pi pi-file-pdf" severity="secondary" size="small" @click="openPdfPopover" />
            <Button label="Dupliquer" icon="pi pi-copy" severity="secondary" size="small" :loading="isDuplicating" @click="handleDuplicate" />
            <Button
              label="Mode Live"
              icon="pi pi-play"
              severity="secondary"
              size="small"
              :disabled="setlist.items.length === 0"
              v-tooltip.top="setlist.items.length === 0 ? 'Ajoutez au moins un titre' : null"
              @click="openLiveMode"
            />
            <Button label="Archiver" icon="pi pi-archive" severity="danger" outlined size="small" @click="confirmArchive" />
          </div>
        </div>
      </div>

      <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">Programme</h3>
          <Button label="Ajouter un titre" icon="pi pi-plus" size="small" @click="addDialogOpen = true" />
        </div>

        <div v-if="setlist.items.length === 0" class="text-center py-8 text-surface-500">
          <i class="pi pi-headphones text-3xl mb-3 block"></i>
          Aucun titre dans cette setlist. Commencez par en ajouter un.
        </div>

        <VueDraggable
          v-else
          v-model="localItems"
          :animation="200"
          ghost-class="opacity-30"
          handle=".cursor-pointer"
          class="flex flex-col gap-2"
          @end="handleDragEnd"
        >
          <SetlistItemCard
            v-for="item in localItems"
            :key="item.id"
            :item="item"
            @edit="openItemEdit"
            @open-menu="openItemMenu"
          />
        </VueDraggable>
      </div>
    </div>

    <Menu ref="itemMenu" :model="itemMenuModel" :popup="true" />

    <AddSetlistItemDialog
      v-model:visible="addDialogOpen"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
      @added="addDialogOpen = false"
    />

    <SetlistItemEditDrawer
      v-model:visible="editDrawerOpen"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
      :item="editingItem"
    />

    <PdfExportPopover
      ref="pdfPopover"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
    />

    <SetlistFileDrawer
      v-model:visible="filesDrawerOpen"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useRouter } from 'vue-router'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import AddSetlistItemDialog from './AddSetlistItemDialog.vue'
import PdfExportPopover from './PdfExportPopover.vue'
import SetlistFileDrawer from './SetlistFileDrawer.vue'
import SetlistItemCard from './SetlistItemCard.vue'
import SetlistItemEditDrawer from './SetlistItemEditDrawer.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const emit = defineEmits(['archived', 'duplicated'])

const setlistsStore = useBandSetlistsStore()
const confirm = useConfirm()
const toast = useToast()
const router = useRouter()

function openLiveMode() {
  router.push({
    name: 'app_band_setlist_live',
    params: { bandSpaceId: props.bandSpaceId, setlistId: props.setlistId }
  })
}

const setlist = computed(() => setlistsStore.activeSetlist)

const localItems = ref([])

watch(
  () => setlist.value?.items,
  (items) => {
    localItems.value = items ? [...items] : []
  },
  { immediate: true }
)

watch(
  () => props.setlistId,
  (id) => {
    if (id) {
      setlistsStore.fetchActive(props.bandSpaceId, id)
    }
  },
  { immediate: true }
)

const totalDurationSeconds = computed(() =>
  localItems.value.reduce(
    (sum, item) => sum + (item.duration_override ?? item.song?.reference_duration ?? 0),
    0
  )
)

const formattedTotalDuration = computed(() => {
  const total = totalDurationSeconds.value
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

// Inline rename
const editingName = ref(false)
const nameDraft = ref('')

function startRename() {
  nameDraft.value = setlist.value?.name ?? ''
  editingName.value = true
}

function cancelRename() {
  editingName.value = false
  nameDraft.value = ''
}

async function commitRename() {
  if (!editingName.value) return
  const trimmed = nameDraft.value.trim()
  editingName.value = false
  if (!trimmed || trimmed === setlist.value?.name) return
  try {
    await setlistsStore.renameSetlist(props.bandSpaceId, props.setlistId, trimmed)
    toast.add({ severity: 'success', summary: 'Setlist renommée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

// Reorder
async function handleDragEnd() {
  const orderedIds = localItems.value.map((i) => i.id)
  try {
    await setlistsStore.reorderItems(props.bandSpaceId, props.setlistId, orderedIds)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

// Add / Edit / Menu
const addDialogOpen = ref(false)
const editDrawerOpen = ref(false)
const editingItem = ref(null)
const itemMenu = ref(null)
const menuTargetItem = ref(null)

function openItemEdit(item) {
  editingItem.value = item
  editDrawerOpen.value = true
}

function openItemMenu(event, item) {
  menuTargetItem.value = item
  itemMenu.value?.toggle(event)
}

const itemMenuModel = computed(() => {
  const item = menuTargetItem.value
  if (!item) return []
  const idx = localItems.value.findIndex((i) => i.id === item.id)
  const isFirst = idx === 0
  const isLast = idx === localItems.value.length - 1
  return [
    { label: 'Modifier', icon: 'pi pi-pencil', command: () => openItemEdit(item) },
    {
      label: 'Monter',
      icon: 'pi pi-arrow-up',
      disabled: isFirst,
      command: () => moveItem(item, -1)
    },
    {
      label: 'Descendre',
      icon: 'pi pi-arrow-down',
      disabled: isLast,
      command: () => moveItem(item, +1)
    },
    { separator: true },
    { label: 'Retirer', icon: 'pi pi-trash', command: () => confirmRemoveItem(item) }
  ]
})

async function moveItem(item, delta) {
  const idx = localItems.value.findIndex((i) => i.id === item.id)
  if (idx < 0) return
  const target = idx + delta
  if (target < 0 || target >= localItems.value.length) return
  const next = [...localItems.value]
  const [moved] = next.splice(idx, 1)
  next.splice(target, 0, moved)
  localItems.value = next
  await handleDragEnd()
}

function confirmRemoveItem(item) {
  confirm.require({
    message: 'Retirer cet élément du setlist ?',
    header: 'Confirmer',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Retirer',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await setlistsStore.removeItem(props.bandSpaceId, props.setlistId, item.id)
        toast.add({ severity: 'success', summary: 'Élément retiré', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}

// PDF + Files
const pdfPopover = ref(null)
const filesDrawerOpen = ref(false)

function openPdfPopover(event) {
  pdfPopover.value?.toggle(event)
}

// Duplicate
const isDuplicating = ref(false)
async function handleDuplicate() {
  isDuplicating.value = true
  try {
    const copy = await setlistsStore.duplicateSetlist(props.bandSpaceId, props.setlistId)
    toast.add({ severity: 'success', summary: 'Setlist dupliquée', life: 3000 })
    emit('duplicated', copy.id)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isDuplicating.value = false
  }
}

// Archive
function confirmArchive() {
  confirm.require({
    message: `Archiver la setlist «${setlist.value?.name}» ?`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await setlistsStore.archiveSetlist(props.bandSpaceId, props.setlistId)
        toast.add({ severity: 'success', summary: 'Setlist archivée', life: 3000 })
        emit('archived')
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}
</script>
