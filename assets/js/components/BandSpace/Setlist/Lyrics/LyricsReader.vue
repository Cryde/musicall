<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    block-scroll
    class="w-full!"
    :show-close-icon="false"
    :pt="{ header: { class: '!p-0' }, content: { class: '!p-0' }, footer: { class: '!p-0' } }"
  >
    <template #header>
      <PhoneBackBar variant="screen" :back-label="t('band_space_setlist.live.back')" :title="song?.title" @back="visible = false">
        <template #actions>
          <Button
            v-if="lyrics"
            icon="pi pi-file-pdf"
            text
            rounded
            severity="secondary"
            :loading="isExporting"
            :aria-label="t('band_space_setlist.lyrics_panel.export_pdf_aria')"
            @click="exportPdf(bandSpaceId, lyrics.id, { chords: true, singers: true, transpose })"
          />
        </template>
      </PhoneBackBar>
    </template>

    <div class="px-5 pt-3 pb-6">
      <p v-if="songFacts" class="mb-2 text-[0.9375rem] text-surface-600 tabular-nums dark:text-surface-300">{{ songFacts }}</p>

      <div v-if="isLoading" class="flex flex-col gap-2 pt-2">
        <Skeleton v-for="i in 6" :key="i" width="100%" height="1.25rem" />
      </div>
      <p v-else-if="!lyrics?.content" class="pt-6 text-center text-surface-600 dark:text-surface-300">
        {{ t('band_space_setlist.phone.no_lyrics') }}
      </p>
      <template v-else>
        <div class="mb-3 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
          {{ t('band_space_setlist.lyrics_panel.transpose') }}
          <Button icon="pi pi-minus" severity="secondary" outlined class="h-9! w-9!" :aria-label="t('band_space_setlist.lyrics_panel.semitone_down_aria')" @click="shift(-1)" />
          <span class="min-w-30 text-center font-medium text-surface-900 tabular-nums dark:text-surface-0" aria-live="polite">{{ transposeLabel }}</span>
          <Button icon="pi pi-plus" severity="secondary" outlined class="h-9! w-9!" :aria-label="t('band_space_setlist.lyrics_panel.semitone_up_aria')" @click="shift(1)" />
        </div>
        <LyricsSheet large :lyrics="lyrics.content" :singers="lyrics.singers" :tonality="song?.tonality ?? null" :transpose="transpose" />
      </template>
    </div>

    <!-- The set goes on: the next song's words one tap away, at rehearsal or on stage. -->
    <template v-if="songs.length > 1" #footer>
      <div class="flex h-17 items-center gap-2 border-t border-surface-200 px-3 pb-[env(safe-area-inset-bottom)] dark:border-surface-700">
        <Button
          icon="pi pi-chevron-left"
          severity="secondary"
          outlined
          class="h-11!"
          :disabled="!step.hasPrevious"
          :aria-label="t('band_space_setlist.live.previous')"
          @click="index = step.index - 1"
        />
        <span class="min-w-0 flex-1 text-center text-sm text-surface-600 tabular-nums dark:text-surface-300">
          <b class="block text-[0.9375rem] text-surface-900 dark:text-surface-0">{{ step.position }}</b>
          <span v-if="step.nextTitle" class="block truncate">{{ t('band_space_setlist.phone.next_up', { title: step.nextTitle }) }}</span>
        </span>
        <Button
          :label="t('band_space_setlist.phone.next_song')"
          icon="pi pi-chevron-right"
          icon-pos="right"
          severity="secondary"
          outlined
          class="h-11!"
          :disabled="!step.hasNext"
          @click="index = step.index + 1"
        />
      </div>
    </template>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Skeleton from 'primevue/skeleton'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceLyricsApi from '../../../../api/bandSpace/band-space-lyrics.js'
import { useLyricsPdf } from '../../../../composables/useLyricsPdf.js'
import { useLyricsTranspose } from '../../../../composables/useLyricsTranspose.js'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import { lyricsReaderStep } from '../../../../utils/lyricsReaderStep.js'
import { formatDuration } from '../../../../utils/setlistDuration.js'
import PhoneBackBar from '../../../Global/PhoneBackBar.vue'
import LyricsSheet from './LyricsSheet.vue'
import './lyrics.css'

/**
 * A song's words on a phone, full screen, and the next song's after them: from a setlist it turns
 * through the set in its running order, from the repertoire it holds the one song. Each song's lyrics
 * are one request, the one its drawer already makes; the key is shifted on screen only.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  // [{ id, title, tonality, tempo, reference_duration }], read in this order
  songs: { type: Array, default: () => [] },
  startIndex: { type: Number, default: 0 }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
const lyricsStore = useBandLyricsStore()

const index = ref(0)
const step = computed(() => lyricsReaderStep(props.songs, index.value))
const song = computed(() => props.songs[step.value.index] ?? null)
const tonality = computed(() => song.value?.tonality ?? null)
const { transpose, transposeLabel, shift } = useLyricsTranspose(tonality)
const { isExporting, exportPdf } = useLyricsPdf()

// « Am · 112 BPM · 3:58 »
const songFacts = computed(() =>
  [
    song.value?.tonality,
    song.value?.tempo
      ? t('band_space_setlist.common.tempo_bpm', { tempo: song.value.tempo })
      : null,
    formatDuration(song.value?.reference_duration)
  ]
    .filter(Boolean)
    .join(' · ')
)

// Closed, the reader forgets what it fetched: the next opening reads the words as they are now.
watch(visible, (open) => {
  if (open) index.value = props.startIndex
  else loaded.clear()
})

const lyrics = ref(null)
const isLoading = ref(false)
const loaded = new Map()
let request = 0

// The words of the song on screen, fetched once per song while the reader is open. Watched by id: a
// live refresh hands over a new copy of the same song, which must not reset the key being read in.
// Every turn takes a token first, so an answer still on its way for a song turned past is dropped.
watch([visible, () => song.value?.id], async ([open, songId], previous) => {
  const token = ++request
  isLoading.value = false
  if (songId !== previous?.[1] || !open) transpose.value = 0
  if (!open || !songId) return
  const listed = lyricsStore.forSong(songId)
  if (!listed) {
    lyrics.value = null
    return
  }
  if (loaded.has(listed.id)) {
    lyrics.value = loaded.get(listed.id)
    return
  }
  isLoading.value = true
  try {
    const full = await bandSpaceLyricsApi.getLyrics(props.bandSpaceId, listed.id)
    loaded.set(listed.id, full)
    if (token === request) lyrics.value = full
  } catch {
    if (token === request) lyrics.value = null
  } finally {
    if (token === request) isLoading.value = false
  }
})
</script>
