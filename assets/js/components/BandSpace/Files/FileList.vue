<template>
  <div>
    <div v-if="isLoading && files.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
    </div>

    <div
      v-else-if="files.length === 0"
      class="flex flex-col items-center justify-center py-16 text-center text-surface-400"
    >
      <i class="pi pi-folder-open text-5xl mb-4"></i>
      <p class="text-sm italic">{{ emptyMessage }}</p>
    </div>

    <div v-else class="flex flex-col">
      <div
        class="hidden md:grid grid-cols-12 gap-2 px-3 py-2 text-xs font-medium uppercase tracking-wide text-surface-400 border-b border-surface-200 dark:border-surface-700"
      >
        <div class="col-span-6">Nom</div>
        <div class="col-span-2">Taille</div>
        <div class="col-span-2">Étiquettes</div>
        <div class="col-span-2">Ajouté le</div>
      </div>

      <div
        v-for="file in files"
        :key="file.id"
        class="grid grid-cols-12 gap-2 px-3 py-2 items-center text-sm border-b border-surface-100 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-800/40 cursor-pointer"
        :draggable="true"
        @click="emit('select', file)"
        @contextmenu="(event) => openContextMenu(event, file)"
        @dragstart="(event) => handleDragStart(event, file)"
        @dragend="handleDragEnd"
      >
        <div class="col-span-12 md:col-span-6 flex items-center gap-2 min-w-0">
          <i :class="iconForMime(file.mime_type)" class="text-lg text-surface-500 shrink-0"></i>
          <span class="truncate font-medium">{{ file.original_name }}</span>
        </div>

        <div class="col-span-4 md:col-span-2 tabular-nums text-surface-600 dark:text-surface-300">
          {{ formatSize(file.size) }}
        </div>

        <div class="col-span-4 md:col-span-2 flex flex-wrap gap-1">
          <Tag
            v-for="tag in file.tags"
            :key="tag.id"
            :value="tag.name"
            :style="tagStyle(tag.color_hex)"
            class="text-xs"
          />
        </div>

        <div class="col-span-4 md:col-span-2 text-surface-600 dark:text-surface-300">
          {{ formatDate(file.creation_datetime) }}
        </div>
      </div>
    </div>

    <ContextMenu ref="contextMenuRef" :model="contextMenuItems" />
  </div>
</template>

<script setup>
import ContextMenu from 'primevue/contextmenu'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  files: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
  emptyMessage: {
    type: String,
    default: 'Aucun fichier dans ce dossier — commencez par en téléverser un.'
  }
})

const emit = defineEmits(['select', 'open-share', 'open-versions', 'open-rename', 'open-move'])

const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const bandSpaceStore = useBandSpaceStore()
const confirm = useConfirm()
const toast = useToast()

const isAdmin = computed(() => bandSpaceStore.getById(props.bandSpaceId)?.role === 'admin')

const contextMenuRef = ref(null)
const contextMenuFile = ref(null)

const canDeleteContextFile = computed(() => {
  const f = contextMenuFile.value
  if (!f) return false
  const userId = userSecurityStore.userProfile?.id
  return f.created_by?.id === userId
})

const contextFileSourceLabel = computed(() => {
  const attachments = contextMenuFile.value?.attachments ?? []
  if (attachments.length === 0) return null
  if (attachments.length > 1) {
    return "Détachez-le d'abord depuis chaque ressource"
  }
  switch (attachments[0]?.source_type) {
    case 'task':
      return "Détachez-le d'abord depuis la tâche"
    case 'finance':
      return "Détachez-le d'abord depuis l'entrée"
    case 'note':
      return "Détachez-le d'abord depuis la note"
    default:
      return "Détachez-le d'abord depuis la ressource concernée"
  }
})

const contextMenuItems = computed(() => {
  const f = contextMenuFile.value
  if (!f) return []
  const isAttached = (f.attachments?.length ?? 0) > 0
  const deleteLabel = isAttached ? `Supprimer (${contextFileSourceLabel.value})` : 'Supprimer'
  const items = [
    { label: 'Ouvrir', icon: 'pi pi-eye', command: () => emit('select', f) },
    {
      label: 'Télécharger',
      icon: 'pi pi-download',
      command: () => f.download_url && window.open(f.download_url, '_blank', 'noopener')
    },
    { label: 'Renommer', icon: 'pi pi-pencil', command: () => emit('open-rename', f) }
  ]
  if (isAdmin.value) {
    items.push({ label: 'Partager', icon: 'pi pi-share-alt', command: () => emit('open-share', f) })
  }
  items.push(
    { label: 'Versions', icon: 'pi pi-history', command: () => emit('open-versions', f) },
    { label: 'Déplacer', icon: 'pi pi-arrows-h', command: () => emit('open-move', f) },
    { separator: true },
    {
      label: deleteLabel,
      icon: 'pi pi-trash',
      class: 'p-menuitem-danger',
      disabled: !canDeleteContextFile.value || isAttached,
      command: () => confirmDelete(f)
    }
  )
  return items
})

function openContextMenu(event, file) {
  event.preventDefault()
  contextMenuFile.value = file
  contextMenuRef.value?.show(event)
}

function confirmDelete(file) {
  confirm.require({
    message: `Supprimer définitivement « ${file.original_name} » ?`,
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await filesStore.deleteFile(props.bandSpaceId, file.id)
        toast.add({ severity: 'success', summary: 'Fichier supprimé', life: 3000 })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Suppression impossible',
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

function handleDragStart(event, file) {
  filesStore.startDrag({
    type: 'file',
    id: file.id,
    folderId: file.folder_id ?? null
  })
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', file.id)
}

function handleDragEnd() {
  filesStore.endDrag()
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}

function formatSize(bytes) {
  if (bytes === null || bytes === undefined) return '—'
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} Go`
}

function formatDate(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function tagStyle(colorHex) {
  if (!colorHex) return {}
  return { backgroundColor: colorHex, color: '#fff' }
}
</script>
