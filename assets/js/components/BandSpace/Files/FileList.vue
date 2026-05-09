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
  </div>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

defineProps({
  files: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
  emptyMessage: {
    type: String,
    default: 'Aucun fichier dans ce dossier — commencez par en téléverser un.'
  }
})

const emit = defineEmits(['select'])

const filesStore = useBandFilesStore()

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
