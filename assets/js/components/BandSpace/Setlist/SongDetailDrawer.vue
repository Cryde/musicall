<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: '420px' }"
    :header="song?.title ?? 'Titre'"
  >
    <div v-if="song" class="flex flex-col gap-4">
      <div class="grid grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-xs uppercase text-surface-500 mb-1">Tonalité</div>
          <div>{{ song.tonality || '—' }}</div>
        </div>
        <div>
          <div class="text-xs uppercase text-surface-500 mb-1">BPM</div>
          <div>{{ song.tempo || '—' }}</div>
        </div>
        <div>
          <div class="text-xs uppercase text-surface-500 mb-1">Durée</div>
          <div>{{ formatDuration(song.reference_duration) }}</div>
        </div>
      </div>

      <div v-if="song.notes">
        <div class="text-xs uppercase text-surface-500 mb-1">Notes</div>
        <p class="text-sm whitespace-pre-line">{{ song.notes }}</p>
      </div>

      <Divider />

      <div>
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs uppercase text-surface-500">Fichiers attachés</div>
          <span v-if="files.length > 0" class="text-xs text-surface-400 tabular-nums">
            {{ files.length }}
          </span>
        </div>

        <div v-if="isLoadingFiles && files.length === 0" class="flex flex-col gap-2 mb-2">
          <Skeleton v-for="i in 2" :key="i" height="2.25rem" borderRadius="0.5rem" />
        </div>

        <ul v-else-if="files.length > 0" class="list-none p-0 m-0 flex flex-col gap-1 mb-3">
          <li
            v-for="file in files"
            :key="file.id"
            class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-surface-50 dark:bg-surface-800 text-sm"
          >
            <i class="pi pi-file text-surface-500 shrink-0" aria-hidden="true"></i>
            <div class="flex-1 min-w-0">
              <div class="truncate">{{ file.original_name }}</div>
              <div v-if="file.size" class="text-xs text-surface-500 tabular-nums">
                {{ formatBytes(file.size) }}
              </div>
            </div>
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              size="small"
              aria-label="Détacher"
              v-tooltip.left="'Détacher ce fichier'"
              @click="confirmDetach(file)"
            />
          </li>
        </ul>

        <p v-else class="text-xs text-surface-400 italic mb-2">
          Aucun fichier pour le moment.
        </p>

        <input ref="fileInput" type="file" class="hidden" @change="handleFileSelected" />
        <Button
          label="Téléverser un fichier"
          icon="pi pi-cloud-upload"
          severity="secondary"
          size="small"
          :loading="isUploading"
          @click="fileInput?.click()"
        />
      </div>

      <Divider />

      <div class="flex gap-2">
        <Button label="Modifier" icon="pi pi-pencil" severity="secondary" @click="emit('edit', song)" />
        <Button label="Archiver" icon="pi pi-archive" severity="danger" outlined @click="confirmArchive" />
      </div>
    </div>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Drawer from 'primevue/drawer'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import bandSpaceSongsApi from '../../../api/bandSpace/band-space-songs.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, default: null }
})

const emit = defineEmits(['edit', 'archived'])
const visible = defineModel('visible', { type: Boolean, default: false })

const songsStore = useBandSongsStore()
const confirm = useConfirm()
const toast = useToast()

const fileInput = ref(null)
const isUploading = ref(false)
const files = ref([])
const isLoadingFiles = ref(false)

function formatDuration(seconds) {
  if (!seconds) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

function formatBytes(bytes) {
  if (!bytes) return ''
  const units = ['o', 'Ko', 'Mo', 'Go']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

async function loadFiles() {
  if (!props.song) {
    files.value = []
    return
  }
  isLoadingFiles.value = true
  try {
    files.value = await bandSpaceSongsApi.getAttachedFiles(props.bandSpaceId, props.song.id)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isLoadingFiles.value = false
  }
}

watch(
  [visible, () => props.song?.id],
  ([isOpen, songId]) => {
    if (isOpen && songId) {
      loadFiles()
    } else if (!isOpen) {
      files.value = []
    }
  },
  { immediate: true }
)

async function handleFileSelected(event) {
  const file = event.target.files?.[0]
  if (!file || !props.song) return
  isUploading.value = true
  try {
    await bandSpaceSongsApi.uploadFile(props.bandSpaceId, props.song.id, file)
    toast.add({ severity: 'success', summary: 'Fichier téléversé', life: 3000 })
    await loadFiles()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function confirmDetach(file) {
  if (!props.song) return
  confirm.require({
    message: `Détacher le fichier « ${file.original_name} » de cette chanson ?`,
    header: 'Confirmer',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Détacher',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await bandSpaceSongsApi.detachFile(props.bandSpaceId, props.song.id, file.id)
        toast.add({ severity: 'success', summary: 'Fichier détaché', life: 3000 })
        await loadFiles()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}

function confirmArchive() {
  if (!props.song) return
  confirm.require({
    message: `Archiver le titre « ${props.song.title} » ? Il sera retiré du répertoire actif.`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await songsStore.deleteSong(props.bandSpaceId, props.song.id)
        toast.add({ severity: 'success', summary: 'Titre archivé', life: 3000 })
        emit('archived')
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}
</script>
