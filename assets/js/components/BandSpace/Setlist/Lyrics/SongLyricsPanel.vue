<template>
  <div>
    <div v-if="isLoading" class="flex flex-col gap-2">
      <div class="text-xs uppercase text-surface-500 mb-2">{{ t('band_space_setlist.lyrics_panel.title') }}</div>
      <Skeleton v-for="i in 4" :key="i" height="1.25rem" />
    </div>

    <LyricsPanel
      v-else-if="lyrics"
      :band-space-id="bandSpaceId"
      :lyrics="lyrics"
      @changed="handleChanged"
    >
      <template #actions>
        <Button
          :label="t('band_space_setlist.lyrics_panel.open_in_lyrics')"
          icon="pi pi-external-link"
          size="small"
          severity="secondary"
          text
          @click="openInLyrics"
        />
      </template>
    </LyricsPanel>

    <div v-else>
      <div class="text-xs uppercase text-surface-500 mb-2">{{ t('band_space_setlist.lyrics_panel.title') }}</div>
      <p class="text-xs text-surface-600 dark:text-surface-300 italic mt-0 mb-2">
        {{ t('band_space_setlist.lyrics_panel.empty') }}
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <Button
          :label="t('band_space_setlist.lyrics_panel.write')"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          :disabled="readOnly"
          @click="isWriting = true"
        />
        <Select
          v-model="lyricsChoice"
          :options="linkable"
          option-label="title"
          option-value="id"
          filter
          :placeholder="t('band_space_setlist.lyrics_panel.link_existing')"
          :aria-label="t('band_space_setlist.lyrics_panel.link_existing')"
          :empty-message="t('band_space_setlist.lyrics_panel.link_existing_empty')"
          :disabled="readOnly || isLinking"
          size="small"
          class="min-w-56"
          @change="link($event.value)"
        />
      </div>
    </div>

    <LyricsEditorDialog
      v-model:visible="isWriting"
      :band-space-id="bandSpaceId"
      :title="song.title"
      :tonality="song.tonality"
      :song-id="song.id"
      @saved="handleChanged"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import bandSpaceLyricsApi from '../../../../api/bandSpace/band-space-lyrics.js'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import { filterLyrics } from '../../../../utils/lyricsList.js'
import LyricsEditorDialog from './LyricsEditorDialog.vue'
import LyricsPanel from './LyricsPanel.vue'

/**
 * A song's lyrics (#1261): the lyrics linked to it, or a way to write some or to link a set already
 * written. Found through the lyrics list, which says which lyrics each song holds.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, required: true },
  /** An archived song gets no new lyrics. */
  readOnly: { type: Boolean, default: false }
})

/** The song changed on the server: it gained or lost its lyrics, or a transposition moved its key. */
const emit = defineEmits(['changed'])

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const lyricsStore = useBandLyricsStore()

const lyrics = ref(null)
const isFetching = ref(false)
const isWriting = ref(false)
const isLinking = ref(false)
const lyricsChoice = ref(null)
let loadToken = 0

const linkedId = computed(() => lyricsStore.forSong(props.song.id)?.id ?? null)
const linkable = computed(() => filterLyrics(lyricsStore.lyrics, { filter: 'unlinked' }))
const isLoading = computed(
  () => isFetching.value || (lyricsStore.isLoading && lyricsStore.lyrics.length === 0)
)

function showError(error) {
  toast.add({
    severity: 'error',
    summary: t('band_space_setlist.common.error'),
    detail: error.message,
    life: 5000
  })
}

async function load(lyricsId) {
  const token = ++loadToken
  lyrics.value = null
  if (!lyricsId) return
  isFetching.value = true
  try {
    const loaded = await bandSpaceLyricsApi.getLyrics(props.bandSpaceId, lyricsId)
    if (token === loadToken) lyrics.value = loaded
  } catch (error) {
    if (token === loadToken) showError(error)
  } finally {
    if (token === loadToken) isFetching.value = false
  }
}

// Read when the drawer shows another song, or when the list arrives after it opened (a deep link).
// Once shown the lyrics stay, as the drawer keeps its copy: a live refetch never swaps or wipes them
// under a member reading or editing.
watch(
  [() => props.song.id, linkedId],
  ([songId, lyricsId], [previousSongId] = []) => {
    if (songId !== previousSongId || !lyrics.value) load(lyricsId)
  },
  { immediate: true }
)

/** The member's own write is the latest copy: a read started meanwhile is dropped. */
function handleChanged(item) {
  loadToken++
  isFetching.value = false
  lyrics.value = item
  emit('changed')
}

async function link(lyricsId) {
  if (!lyricsId) return
  isLinking.value = true
  try {
    handleChanged(await lyricsStore.linkLyrics(props.bandSpaceId, lyricsId, props.song.id))
  } catch (error) {
    showError(error)
  } finally {
    isLinking.value = false
    lyricsChoice.value = null
  }
}

function openInLyrics() {
  router.replace({ query: { view: 'lyrics', lyrics: lyrics.value.id } })
}
</script>
