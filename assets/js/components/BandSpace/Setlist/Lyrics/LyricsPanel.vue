<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
      <div class="text-xs uppercase text-surface-500">{{ t('band_space_setlist.lyrics_panel.title') }}</div>
      <div class="flex items-center gap-1">
        <slot name="actions" />
        <template v-if="lyrics.content">
          <Button
            icon="pi pi-window-maximize"
            size="small"
            severity="secondary"
            text
            rounded
            :aria-label="t('band_space_setlist.lyrics_panel.enlarge_aria')"
            v-tooltip.bottom="t('band_space_setlist.lyrics_panel.enlarge')"
            @click="isReading = true"
          />
          <Button
            icon="pi pi-file-pdf"
            size="small"
            severity="secondary"
            text
            rounded
            :aria-label="t('band_space_setlist.lyrics_panel.export_pdf_aria')"
            v-tooltip.bottom="t('band_space_setlist.lyrics_panel.export_pdf')"
            @click="pdfPopover?.toggle($event)"
          />
          <Button
            icon="pi pi-pencil"
            size="small"
            severity="secondary"
            text
            rounded
            :aria-label="t('band_space_setlist.lyrics_panel.edit_aria')"
            v-tooltip.bottom="t('band_space_setlist.lyrics_panel.edit')"
            :disabled="readOnly"
            @click="isEditing = true"
          />
        </template>
      </div>
    </div>

    <template v-if="lyrics.content">
      <div class="flex flex-wrap items-center gap-2 mb-3 text-sm">
        <span class="text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.lyrics_panel.transpose') }}</span>
        <Button icon="pi pi-minus" size="small" severity="secondary" outlined :aria-label="t('band_space_setlist.lyrics_panel.semitone_down_aria')" @click="shift(-1)" />
        <span class="min-w-24 text-center tabular-nums" aria-live="polite">{{ transposeLabel }}</span>
        <Button icon="pi pi-plus" size="small" severity="secondary" outlined :aria-label="t('band_space_setlist.lyrics_panel.semitone_up_aria')" @click="shift(1)" />
        <Button
          v-if="transpose !== 0 && !readOnly"
          :label="t('band_space_setlist.lyrics_panel.save_transposition')"
          size="small"
          severity="secondary"
          text
          :loading="isTransposing"
          @click="saveTransposition"
        />
      </div>
      <LyricsSheet :lyrics="lyrics.content" :singers="lyrics.singers" :tonality="tonality" :transpose="transpose" />
    </template>

    <div v-else class="flex flex-col items-start gap-2">
      <p class="text-xs text-surface-600 dark:text-surface-300 italic m-0">
        {{ t('band_space_setlist.lyrics_panel.empty') }}
      </p>
      <Button
        :label="t('band_space_setlist.lyrics_panel.add')"
        icon="pi pi-plus"
        severity="secondary"
        size="small"
        :disabled="readOnly"
        @click="isEditing = true"
      />
    </div>

    <Popover ref="pdfPopover">
      <div class="flex flex-col gap-3 w-64">
        <div class="flex items-center gap-2">
          <Checkbox v-model="pdfOptions.chords" :binary="true" :input-id="`${idPrefix}-pdf-chords`" />
          <label :for="`${idPrefix}-pdf-chords`" class="text-sm">{{ t('band_space_setlist.pdf_export.lyrics_chords') }}</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="pdfOptions.singers" :binary="true" :input-id="`${idPrefix}-pdf-singers`" />
          <label :for="`${idPrefix}-pdf-singers`" class="text-sm">{{ t('band_space_setlist.pdf_export.lyrics_singers') }}</label>
        </div>
        <p v-if="transpose !== 0" class="text-xs text-surface-600 dark:text-surface-300 m-0">
          {{ t('band_space_setlist.lyrics_panel.pdf_transposed', { transposition: transposeLabel }) }}
        </p>
        <Button
          :label="isExporting ? t('band_space_setlist.pdf_export.generating') : t('band_space_setlist.pdf_export.download')"
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
      :header="sheetTitle"
      :style="{ width: 'min(60rem, 100vw)' }"
      :breakpoints="{ '768px': '100vw' }"
    >
      <LyricsSheet
        v-if="lyrics.content"
        :lyrics="lyrics.content"
        :singers="lyrics.singers"
        :tonality="tonality"
        :transpose="transpose"
        large
      />
    </Dialog>

    <LyricsEditorDialog
      v-model:visible="isEditing"
      :band-space-id="bandSpaceId"
      :title="sheetTitle"
      :tonality="tonality"
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
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceLyricsApi from '../../../../api/bandSpace/band-space-lyrics.js'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import { transposeKey } from '../../../../utils/chordpro.js'
import { downloadBlob } from '../../../../utils/downloadBlob.js'
import { isLyricsReadOnly } from '../../../../utils/lyricsList.js'
import LyricsEditorDialog from './LyricsEditorDialog.vue'
import LyricsSheet from './LyricsSheet.vue'
import './lyrics.css'

/** One set of lyrics to read, transpose, print and edit: in a song's drawer and in « Paroles ». */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** The lyrics item: { id, title, content, version, singers, song }. */
  lyrics: { type: Object, required: true }
})

/** The lyrics as the server now has them, after a save or a transposition. */
const emit = defineEmits(['changed'])

const { t } = useI18n()
const toast = useToast()
const lyricsStore = useBandLyricsStore()
const idPrefix = useId()

const isEditing = ref(false)
const isReading = ref(false)
const transpose = ref(0)
const isTransposing = ref(false)
const pdfPopover = ref(null)
const isExporting = ref(false)
const pdfOptions = reactive({ chords: true, singers: true })

const readOnly = computed(() => isLyricsReadOnly(props.lyrics))

/** Unlinked lyrics have no key: the chords move on their own. */
const tonality = computed(() => props.lyrics.song?.tonality ?? null)
const sheetTitle = computed(() => props.lyrics.song?.title ?? props.lyrics.title)

watch(
  () => props.lyrics.id,
  () => {
    transpose.value = 0
  }
)

const transposeLabel = computed(() => {
  if (transpose.value === 0) return t('band_space_setlist.lyrics_panel.original_key')
  const semitones = `${transpose.value > 0 ? '+' : ''}${transpose.value}`
  const count = Math.abs(transpose.value)
  const key = transposeKey(tonality.value, transpose.value)
  return key
    ? t('band_space_setlist.lyrics_panel.semitones_with_key', { semitones, key }, count)
    : t('band_space_setlist.lyrics_panel.semitones', { semitones }, count)
})

// Kept inside an octave: twelve semitones up is where it started.
function shift(step) {
  const next = transpose.value + step
  transpose.value = next > 11 || next < -11 ? 0 : next
}

async function saveTransposition() {
  isTransposing.value = true
  try {
    const transposed = await lyricsStore.transposeLyrics(
      props.bandSpaceId,
      props.lyrics.id,
      transpose.value,
      props.lyrics.version
    )
    transpose.value = 0
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.lyrics_panel.transposition_saved'),
      life: 3000
    })
    emit('changed', transposed)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.lyrics_panel.transposition_failed'),
      detail: error.message,
      life: 6000
    })
  } finally {
    isTransposing.value = false
  }
}

function handleSaved(saved) {
  transpose.value = 0
  emit('changed', saved)
}

async function exportPdf() {
  if (isExporting.value) return
  isExporting.value = true
  try {
    const { blob, filename } = await bandSpaceLyricsApi.downloadPdf(
      props.bandSpaceId,
      props.lyrics.id,
      { ...pdfOptions, transpose: transpose.value }
    )
    downloadBlob(blob, filename ?? 'paroles.pdf')
    pdfPopover.value?.hide()
  } catch (error) {
    // A 401 already sends the member to log in; the body of a 5xx says nothing usable.
    if (error?.response?.status === 401) return
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.pdf_export.failed_summary'),
      detail: t('band_space_setlist.pdf_export.failed_detail'),
      life: 6000
    })
  } finally {
    isExporting.value = false
  }
}

function edit() {
  if (!readOnly.value) isEditing.value = true
}

defineExpose({ edit })
</script>
