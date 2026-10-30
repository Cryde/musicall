<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: 'min(40rem, 100vw)' }"
  >
    <template #header>
      <InlineEditField
        v-if="item"
        :model-value="item.title"
        :label="t('band_space_setlist.lyrics_drawer.title')"
        required
        class="flex-1 mr-2"
        input-class="font-semibold text-lg"
        :readonly="readOnly"
        :save="rename"
      >
        <template #default="{ value }"><span class="text-xl font-semibold">{{ value }}</span></template>
      </InlineEditField>
    </template>

    <div v-if="isLoading && !item" class="flex flex-col gap-2">
      <Skeleton v-for="i in 5" :key="i" height="1.25rem" />
    </div>

    <div v-else-if="item" class="flex flex-col gap-4">
      <div>
        <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">{{ t('band_space_setlist.lyrics_drawer.song') }}</div>
        <div v-if="item.song" class="flex flex-wrap items-center gap-2">
          <!-- A song in the trash has no drawer to open. -->
          <span v-if="item.song.archive_datetime" class="font-medium">{{ item.song.title }}</span>
          <button
            v-else
            type="button"
            class="font-medium hover:underline text-left"
            :aria-label="t('band_space_setlist.lyrics_drawer.open_song_aria', { title: item.song.title })"
            @click="openSong"
          >
            {{ item.song.title }}
          </button>
          <Tag v-if="item.song.archive_datetime" :value="t('band_space_setlist.lyrics_drawer.song_archived')" severity="secondary" />
          <Button
            :label="t('band_space_setlist.lyrics_drawer.unlink')"
            icon="pi pi-link"
            size="small"
            severity="secondary"
            text
            :loading="isLinking"
            :disabled="item.archive_datetime != null"
            @click="unlink"
          />
        </div>
        <div v-else class="flex flex-wrap items-center gap-2">
          <span class="text-sm italic text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.lyrics_view.unlinked') }}</span>
          <Select
            v-model="songChoice"
            :options="songs"
            option-label="title"
            option-value="id"
            filter
            :placeholder="t('band_space_setlist.lyrics_drawer.link')"
            :aria-label="t('band_space_setlist.lyrics_drawer.link')"
            :empty-message="t('band_space_setlist.lyrics_drawer.link_empty')"
            :disabled="readOnly || isLinking"
            size="small"
            class="min-w-56"
            @change="link($event.value)"
          />
        </div>
        <p v-if="item.song?.archive_datetime" class="text-xs text-surface-600 dark:text-surface-300 mt-2 mb-0">
          {{ t('band_space_setlist.lyrics_drawer.song_archived_hint') }}
        </p>
      </div>

      <Divider />

      <LyricsPanel
        ref="panel"
        :band-space-id="bandSpaceId"
        :lyrics="item"
        @changed="handleChanged"
      />

      <template v-if="!item.archive_datetime">
        <Divider />
        <div class="flex gap-2">
          <Button :label="t('band_space_setlist.lyrics_drawer.archive')" icon="pi pi-archive" severity="danger" outlined @click="confirmArchive" />
        </div>
      </template>
    </div>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Drawer from 'primevue/drawer'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import bandSpaceLyricsApi from '../../../../api/bandSpace/band-space-lyrics.js'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import { useBandSongsStore } from '../../../../store/bandSpace/bandSpaceSongs.js'
import { isLyricsReadOnly, linkableSongs } from '../../../../utils/lyricsList.js'
import InlineEditField from '../Song/InlineEditField.vue'
import LyricsPanel from './LyricsPanel.vue'

/**
 * One set of lyrics in « Paroles » (#1261): its title, the song it is linked to, and the lyrics
 * themselves. It keeps the copy it opened with, so a live refetch of the list never swaps it.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** A list item, which the drawer reads in full, or new lyrics already whole. */
  lyrics: { type: Object, default: null },
  /** Fresh lyrics open straight in the editor. */
  editOnOpen: { type: Boolean, default: false }
})

const emit = defineEmits(['archived'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const router = useRouter()
const lyricsStore = useBandLyricsStore()
const songsStore = useBandSongsStore()

const item = ref(null)
const isLoading = ref(false)
const isLinking = ref(false)
const songChoice = ref(null)
const panel = ref(null)
let loadToken = 0

const readOnly = computed(() => isLyricsReadOnly(item.value))
const songs = computed(() => linkableSongs(songsStore.songs, lyricsStore.lyrics))

function showError(error) {
  toast.add({
    severity: 'error',
    summary: t('band_space_setlist.common.error'),
    detail: error.message,
    life: 5000
  })
}

watch(
  [visible, () => props.lyrics?.id],
  async ([isOpen, lyricsId]) => {
    if (!isOpen || !lyricsId) return
    // A slower read for lyrics opened before must not land on these ones.
    const token = ++loadToken
    if (item.value?.id !== lyricsId) item.value = null
    isLoading.value = true
    try {
      const loaded =
        'content' in props.lyrics
          ? props.lyrics
          : await bandSpaceLyricsApi.getLyrics(props.bandSpaceId, lyricsId)
      if (token !== loadToken) return
      item.value = loaded
      if (props.editOnOpen) {
        await nextTick()
        panel.value?.edit()
      }
    } catch (error) {
      if (token === loadToken) showError(error)
    } finally {
      if (token === loadToken) isLoading.value = false
    }
  },
  { immediate: true }
)

function handleChanged(changed) {
  item.value = changed
}

/** Rethrown to the field, which shows the refusal under it. */
async function rename(title) {
  handleChanged(await lyricsStore.updateLyrics(props.bandSpaceId, item.value.id, { title }))
}

async function link(songId) {
  if (!songId) return
  isLinking.value = true
  try {
    handleChanged(await lyricsStore.linkLyrics(props.bandSpaceId, item.value.id, songId))
  } catch (error) {
    showError(error)
  } finally {
    isLinking.value = false
    songChoice.value = null
  }
}

async function unlink() {
  isLinking.value = true
  try {
    handleChanged(
      await lyricsStore.unlinkLyrics(props.bandSpaceId, item.value.id, item.value.song?.id)
    )
  } catch (error) {
    showError(error)
  } finally {
    isLinking.value = false
  }
}

function openSong() {
  router.replace({ query: { song: item.value.song.id } })
}

function confirmArchive() {
  confirm.require({
    message: t('band_space_setlist.lyrics_drawer.archive_confirm_message', {
      title: item.value.title
    }),
    header: t('band_space_setlist.lyrics_drawer.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.lyrics_drawer.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await lyricsStore.archiveLyrics(props.bandSpaceId, item.value.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.lyrics_drawer.archived'),
          life: 3000
        })
        emit('archived')
      } catch (error) {
        showError(error)
      }
    }
  })
}
</script>
