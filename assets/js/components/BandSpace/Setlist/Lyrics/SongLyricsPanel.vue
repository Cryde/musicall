<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
      <div class="text-xs uppercase text-surface-500">Paroles</div>
      <div v-if="lyrics?.lyrics" class="flex items-center gap-1">
        <Button
          icon="pi pi-window-maximize"
          size="small"
          severity="secondary"
          text
          rounded
          aria-label="Agrandir les paroles"
          v-tooltip.bottom="'Agrandir'"
          @click="isReading = true"
        />
        <Button
          icon="pi pi-file-pdf"
          size="small"
          severity="secondary"
          text
          rounded
          aria-label="Exporter les paroles en PDF"
          v-tooltip.bottom="'Exporter en PDF'"
          @click="pdfPopover?.toggle($event)"
        />
        <Button
          icon="pi pi-pencil"
          size="small"
          severity="secondary"
          text
          rounded
          aria-label="Éditer les paroles"
          v-tooltip.bottom="'Éditer'"
          :disabled="readOnly"
          @click="isEditing = true"
        />
      </div>
    </div>

    <div v-if="isLoading" class="flex flex-col gap-2">
      <Skeleton v-for="i in 4" :key="i" height="1.25rem" />
    </div>

    <template v-else-if="lyrics?.lyrics">
      <div class="flex flex-wrap items-center gap-2 mb-3 text-sm">
        <span class="text-surface-600 dark:text-surface-300">Transposer</span>
        <Button icon="pi pi-minus" size="small" severity="secondary" outlined aria-label="Descendre d'un demi-ton" @click="shift(-1)" />
        <span class="min-w-24 text-center tabular-nums" aria-live="polite">{{ transposeLabel }}</span>
        <Button icon="pi pi-plus" size="small" severity="secondary" outlined aria-label="Monter d'un demi-ton" @click="shift(1)" />
        <Button
          v-if="transpose !== 0 && !readOnly"
          label="Enregistrer dans cette tonalité"
          size="small"
          severity="secondary"
          text
          :loading="isTransposing"
          @click="saveTransposition"
        />
      </div>
      <LyricsSheet :lyrics="lyrics.lyrics" :singers="lyrics.singers" :tonality="lyrics.tonality" :transpose="transpose" />
    </template>

    <div v-else class="flex flex-col items-start gap-2">
      <p class="text-xs text-surface-600 dark:text-surface-300 italic m-0">
        Pas encore de paroles. Collez-les depuis un site d'accords, les accords suivent.
      </p>
      <Button
        label="Ajouter les paroles"
        icon="pi pi-plus"
        severity="secondary"
        size="small"
        :disabled="readOnly || isLoading"
        @click="isEditing = true"
      />
    </div>

    <Popover ref="pdfPopover">
      <div class="flex flex-col gap-3 w-64">
        <div class="flex items-center gap-2">
          <Checkbox v-model="pdfOptions.chords" :binary="true" input-id="song-pdf-chords" />
          <label for="song-pdf-chords" class="text-sm">Avec les accords</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="pdfOptions.singers" :binary="true" input-id="song-pdf-singers" />
          <label for="song-pdf-singers" class="text-sm">Avec qui chante</label>
        </div>
        <p v-if="transpose !== 0" class="text-xs text-surface-600 dark:text-surface-300 m-0">
          Transposé de {{ transposeLabel }}, comme à l'écran.
        </p>
        <Button
          :label="isExporting ? 'Génération...' : 'Télécharger le PDF'"
          :icon="isExporting ? 'pi pi-spin pi-spinner' : 'pi pi-download'"
          :disabled="isExporting"
          @click="exportPdf"
        />
      </div>
    </Popover>

    <Dialog
      v-model:visible="isReading"
      modal
      maximizable
      :header="song.title"
      :style="{ width: 'min(60rem, 100vw)' }"
      :breakpoints="{ '768px': '100vw' }"
    >
      <LyricsSheet
        v-if="lyrics?.lyrics"
        :lyrics="lyrics.lyrics"
        :singers="lyrics.singers"
        :tonality="lyrics.tonality"
        :transpose="transpose"
        large
      />
    </Dialog>

    <LyricsEditorDialog
      v-model:visible="isEditing"
      :band-space-id="bandSpaceId"
      :song="song"
      :lyrics="lyrics"
      @saved="handleSaved"
      @reloaded="handleSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Popover from 'primevue/popover'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import bandSpaceSongsApi from '../../../../api/bandSpace/band-space-songs.js'
import { transposeKey } from '../../../../utils/chordpro.js'
import { downloadBlob } from '../../../../utils/downloadBlob.js'
import LyricsEditorDialog from './LyricsEditorDialog.vue'
import LyricsSheet from './LyricsSheet.vue'
import './lyrics.css'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, required: true },
  /** An archived song is read only. */
  readOnly: { type: Boolean, default: false }
})

/** The song changed on the server: it gained or lost its lyrics, or a transposition moved its key. */
const emit = defineEmits(['changed'])

const toast = useToast()

const lyrics = ref(null)
const isLoading = ref(false)
const isEditing = ref(false)
const isReading = ref(false)
const transpose = ref(0)
const isTransposing = ref(false)
const pdfPopover = ref(null)
const isExporting = ref(false)
const pdfOptions = reactive({ chords: true, singers: true })

async function load() {
  transpose.value = 0
  if (!props.song.has_lyrics) {
    lyrics.value = null
    return
  }
  isLoading.value = true
  try {
    lyrics.value = await bandSpaceSongsApi.getLyrics(props.bandSpaceId, props.song.id)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 5000 })
  } finally {
    isLoading.value = false
  }
}

watch(() => props.song.id, load, { immediate: true })

const transposeLabel = computed(() => {
  if (transpose.value === 0) return "Tonalité d'origine"
  const steps = `${transpose.value > 0 ? '+' : ''}${transpose.value} demi-ton${Math.abs(transpose.value) > 1 ? 's' : ''}`
  const key = transposeKey(lyrics.value?.tonality, transpose.value)
  return key ? `${steps} (${key})` : steps
})

// Kept inside an octave: twelve semitones up is where it started.
function shift(step) {
  const next = transpose.value + step
  transpose.value = next > 11 || next < -11 ? 0 : next
}

async function saveTransposition() {
  isTransposing.value = true
  try {
    lyrics.value = await bandSpaceSongsApi.transposeLyrics(
      props.bandSpaceId,
      props.song.id,
      transpose.value,
      lyrics.value.lyrics_version
    )
    transpose.value = 0
    toast.add({ severity: 'success', summary: 'Tonalité enregistrée', life: 3000 })
    emit('changed')
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Transposition impossible',
      detail: error.message,
      life: 6000
    })
  } finally {
    isTransposing.value = false
  }
}

function handleSaved(saved) {
  const hadLyrics = Boolean(lyrics.value?.lyrics)
  lyrics.value = saved
  transpose.value = 0
  if (hadLyrics !== Boolean(saved.lyrics)) emit('changed')
}

async function exportPdf() {
  if (isExporting.value) return
  isExporting.value = true
  try {
    const { blob, filename } = await bandSpaceSongsApi.downloadPdf(
      props.bandSpaceId,
      props.song.id,
      {
        ...pdfOptions,
        transpose: transpose.value
      }
    )
    downloadBlob(blob, filename ?? 'paroles.pdf')
    pdfPopover.value?.hide()
  } catch (error) {
    // A 401 already sends the member to log in; the body of a 5xx says nothing usable.
    if (error?.response?.status === 401) return
    toast.add({
      severity: 'error',
      summary: 'Export impossible',
      detail: 'Le PDF n’a pas pu être généré. Veuillez réessayer dans un instant.',
      life: 6000
    })
  } finally {
    isExporting.value = false
  }
}
</script>
