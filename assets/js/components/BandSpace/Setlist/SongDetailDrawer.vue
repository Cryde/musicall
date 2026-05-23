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
        <div class="text-xs uppercase text-surface-500 mb-2">Fichiers attachés</div>
        <p class="text-xs text-surface-400 italic mb-2">
          Téléversez des partitions, accords, audio… Ils apparaitront dans le dossier virtuel « Chansons ».
        </p>
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          @change="handleFileSelected"
        />
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
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
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

function formatDuration(seconds) {
  if (!seconds) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

async function handleFileSelected(event) {
  const file = event.target.files?.[0]
  if (!file || !props.song) return
  isUploading.value = true
  try {
    await bandSpaceSongsApi.uploadFile(props.bandSpaceId, props.song.id, file)
    toast.add({ severity: 'success', summary: 'Fichier téléversé', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
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
