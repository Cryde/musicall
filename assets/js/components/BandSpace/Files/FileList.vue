<template>
  <div>
    <div v-if="isLoading && files.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
    </div>

    <div v-else-if="files.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-surface-400">
      <i class="pi pi-folder-open text-5xl mb-4"></i>
      <p class="text-sm italic">{{ emptyMessage }}</p>
    </div>

    <DataTable
      v-else
      :value="files"
      data-key="id"
      :show-headers="true"
      class="text-sm"
      selection-mode="single"
      @row-click="handleRowClick"
      :pt="{
        bodyRow: { class: 'hover:bg-surface-50 dark:hover:bg-surface-800/40 cursor-pointer' }
      }"
    >
      <Column field="original_name" header="Nom">
        <template #body="{ data }">
          <div class="flex items-center gap-2 min-w-0">
            <i :class="iconForMime(data.mime_type)" class="text-lg text-surface-500"></i>
            <span class="truncate font-medium">{{ data.original_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="size" header="Taille" headerStyle="width:8rem">
        <template #body="{ data }">
          <span class="tabular-nums text-surface-600 dark:text-surface-300">{{ formatSize(data.size) }}</span>
        </template>
      </Column>

      <Column field="tags" header="Étiquettes" headerStyle="width:14rem">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1">
            <Tag
              v-for="tag in data.tags"
              :key="tag.id"
              :value="tag.name"
              :style="tagStyle(tag.color_hex)"
              class="text-xs"
            />
          </div>
        </template>
      </Column>

      <Column field="creation_datetime" header="Ajouté le" headerStyle="width:10rem">
        <template #body="{ data }">
          <span class="text-surface-600 dark:text-surface-300">{{ formatDate(data.creation_datetime) }}</span>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'

defineProps({
  files: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
  emptyMessage: {
    type: String,
    default: 'Aucun fichier dans ce dossier — commencez par en téléverser un.'
  }
})

const emit = defineEmits(['select'])

function handleRowClick(event) {
  emit('select', event.data)
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
