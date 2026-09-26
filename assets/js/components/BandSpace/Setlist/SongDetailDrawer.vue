<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: 'min(40rem, 100vw)' }"
  >
    <!-- Every value is edited where it is shown (#1067): there is no separate edit form. -->
    <template #header>
      <InlineEditField
        v-if="song"
        :model-value="song.title"
        label="Titre"
        required
        class="flex-1 mr-2"
        input-class="font-semibold text-lg"
        :readonly="isLocked('title')"
        :save="(value) => saveField('title', value)"
      >
        <template #default="{ value }"><span class="text-xl font-semibold">{{ value }}</span></template>
      </InlineEditField>
    </template>

    <div v-if="song" class="flex flex-col gap-4">
      <div class="grid grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">Tonalité</div>
          <InlineEditField
            :model-value="song.tonality"
            label="Tonalité"
            placeholder="ex. Em"
            :readonly="isLocked('tonality')"
            :save="(value) => saveField('tonality', value)"
          />
        </div>
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">BPM</div>
          <InlineEditField
            :model-value="song.tempo"
            label="BPM"
            kind="number"
            :readonly="isLocked('tempo')"
            :save="(value) => saveField('tempo', value)"
          />
        </div>
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">Durée</div>
          <InlineEditField
            :model-value="song.reference_duration"
            label="Durée"
            kind="duration"
            :readonly="isLocked('reference_duration')"
            :save="(value) => saveField('reference_duration', value)"
          />
        </div>
      </div>

      <div>
        <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">Notes</div>
        <InlineEditField
          :model-value="song.notes"
          label="Notes"
          multiline
          placeholder="Ajouter une note…"
          class="text-sm"
          :readonly="isLocked('notes')"
          :save="(value) => saveField('notes', value)"
        />
      </div>

      <Divider />

      <SongLyricsPanel
        :band-space-id="bandSpaceId"
        :song="song"
        :read-only="readOnly"
        @changed="handleLyricsChanged"
      />

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
          label="Importer un fichier"
          icon="pi pi-cloud-upload"
          severity="secondary"
          size="small"
          :loading="isUploading"
          @click="fileInput?.click()"
        />
      </div>

      <Divider />

      <div v-if="!readOnly" class="flex gap-2">
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
import { computed, ref, watch } from 'vue'
import bandSpaceSongsApi from '../../../api/bandSpace/band-space-songs.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import SongLyricsPanel from './Lyrics/SongLyricsPanel.vue'
import InlineEditField from './Song/InlineEditField.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, default: null }
})

const emit = defineEmits(['archived', 'updated'])
const visible = defineModel('visible', { type: Boolean, default: false })

const songsStore = useBandSongsStore()
const confirm = useConfirm()
const toast = useToast()

const fileInput = ref(null)
const isUploading = ref(false)
const files = ref([])
const isLoadingFiles = ref(false)

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

const readOnly = computed(() => props.song?.archive_datetime != null)

/**
 * One field per PATCH, and one PATCH at a time: the server writes back every field of the song it
 * read, so two saves in flight could put the first one's field back. The other fields wait meanwhile.
 * A refusal is rethrown to the field.
 */
const savingField = ref(null)

function isLocked(field) {
  return readOnly.value || (savingField.value !== null && savingField.value !== field)
}

async function saveField(field, value) {
  savingField.value = field
  try {
    emit(
      'updated',
      await songsStore.updateSong(props.bandSpaceId, props.song.id, { [field]: value })
    )
  } finally {
    savingField.value = null
  }
}

// The lyrics live on their own resource; the song only learns about them (has_lyrics, a moved key) here.
async function handleLyricsChanged() {
  try {
    emit('updated', await songsStore.refreshSong(props.bandSpaceId, props.song.id))
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
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
