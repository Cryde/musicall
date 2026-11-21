<template>
  <div class="flex flex-col gap-5 max-md:gap-4">
    <div v-if="loadError" class="flex flex-col items-start gap-3">
      <Message severity="error" :closable="false">{{ loadError }}</Message>
      <Button :label="backAria" icon="pi pi-arrow-left" severity="secondary" @click="goBack" />
    </div>

    <template v-else-if="song">
      <!-- The way back, not the heading: the song is what this page is about. -->
      <PhoneBackBar v-if="isBelowMd" variant="page" :back-label="backAria" @back="goBack">
        <template #title>
          <span class="min-w-0 truncate text-base font-medium text-surface-600 dark:text-surface-300">{{ backName }}</span>
        </template>
      </PhoneBackBar>
      <button
        v-else
        type="button"
        class="self-start inline-flex items-center gap-1.5 border-0 bg-transparent p-0 text-sm font-medium text-surface-600 hover:underline dark:text-surface-300"
        :aria-label="backAria"
        @click="goBack"
      >
        <i class="pi pi-arrow-left text-xs" aria-hidden="true" />{{ backName }}
      </button>

      <header class="flex flex-wrap items-start gap-x-4 gap-y-3">
        <div class="flex min-w-0 flex-1 flex-col gap-3">
          <!-- The heading for assistive technology; the title on screen is the button that edits it. -->
          <h1 class="sr-only">{{ song.title }}</h1>
          <InlineEditField
            :model-value="song.title"
            :label="t('band_space_setlist.song_drawer.title')"
            required
            input-class="text-xl font-bold"
            :readonly="isLocked('title')"
            :save="(value) => saveField('title', value)"
          >
            <template #default="{ value }">
              <span class="text-2xl font-bold leading-tight text-surface-900 md:text-3xl dark:text-surface-0" aria-hidden="true">{{ value }}</span>
            </template>
          </InlineEditField>

          <div class="flex flex-wrap items-start gap-2">
            <InlineEditField
              v-for="fact in shownFacts"
              :key="fact.key"
              :model-value="song[fact.key]"
              :label="t(fact.labelKey)"
              :kind="fact.kind"
              :placeholder="fact.placeholderKey ? t(fact.placeholderKey) : undefined"
              inline
              input-class="w-32"
              :readonly="isLocked(fact.key)"
              :save="(value) => saveField(fact.key, value)"
            >
              <template #default="{ display, isEmpty }">
                <span :class="['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm', isEmpty ? MISSING_CHIP : VALUE_CHIP]">
                  <i :class="['pi text-xs', fact.icon]" aria-hidden="true" />
                  <template v-if="isEmpty">{{ t('band_space_setlist.song_page.add_value', { label: t(fact.labelKey) }) }}</template>
                  <template v-else>{{ display }}<span v-if="fact.unit" class="text-surface-600 dark:text-surface-300">&nbsp;{{ fact.unit }}</span></template>
                </span>
              </template>
            </InlineEditField>
            <span v-if="!isBelowMd" class="flex flex-wrap gap-1.5 self-center">
              <RouterLink
                v-for="setlist in song.setlists ?? []"
                :key="setlist.id"
                :to="setlistRoute(setlist.id)"
                class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-700 no-underline hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:hover:bg-surface-700"
              >
                <i class="pi pi-list text-[0.65rem] text-rose-600" aria-hidden="true" />{{ setlist.name }}
              </RouterLink>
            </span>
          </div>

          <InlineEditField
            v-if="!isBelowMd"
            :model-value="song.notes"
            :label="t('band_space_setlist.song_drawer.notes')"
            multiline
            :placeholder="t('band_space_setlist.song_drawer.notes_placeholder')"
            class="max-w-3xl text-sm"
            :readonly="isLocked('notes')"
            :save="(value) => saveField('notes', value)"
          />
        </div>

        <div class="flex items-center gap-2">
          <Button
            v-if="tab && !isBelowMd"
            :label="t('band_space_files.attached_section.read_tab')"
            icon="pi pi-play"
            @click="readTab"
          />
          <Button
            v-if="!readOnly"
            icon="pi pi-ellipsis-v"
            text
            rounded
            severity="secondary"
            aria-haspopup="true"
            :aria-label="t('band_space_setlist.song_page.actions', { title: song.title })"
            @click="menu?.toggle($event)"
          />
          <Menu ref="menu" :model="menuItems" :pt="DANGER_MENU_PT" popup />
        </div>
        <Button
          v-if="tab && isBelowMd"
          :label="t('band_space_files.attached_section.read_tab')"
          icon="pi pi-play"
          class="w-full"
          size="large"
          @click="readTab"
        />
      </header>

      <!-- A phone shows one part at a time, the files first: what the band opens at rehearsal. -->
      <div
        v-if="isBelowMd"
        role="tablist"
        :aria-label="t('band_space_setlist.song_page.tabs')"
        class="flex gap-1 rounded-xl bg-surface-100 p-1 dark:bg-surface-800"
      >
        <button
          v-for="tabName in PHONE_TABS"
          :id="`song-tab-${tabName.key}`"
          :key="tabName.key"
          type="button"
          role="tab"
          :aria-selected="phoneTab === tabName.key"
          :aria-controls="`song-panel-${tabName.key}`"
          :tabindex="phoneTab === tabName.key ? 0 : -1"
          :class="[
            'h-10 flex-1 rounded-lg border-0 text-sm font-semibold',
            phoneTab === tabName.key ? 'bg-surface-0 text-surface-900 shadow-sm dark:bg-surface-900 dark:text-surface-0' : 'bg-transparent text-surface-600 dark:text-surface-300'
          ]"
          @click="phoneTab = tabName.key"
          @keydown.left.prevent="moveTab(-1)"
          @keydown.right.prevent="moveTab(1)"
        >
          {{ tabName.key === 'rehearse' && fileCount > 0 ? t('band_space_setlist.song_page.tab_rehearse_count', { count: fileCount }) : t(tabName.labelKey) }}
        </button>
      </div>

      <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <!-- The files' heading reads like the lyrics' one beside it. -->
        <section
          v-show="!isBelowMd || phoneTab === 'rehearse'"
          v-bind="phonePanel('rehearse')"
          :class="CARD"
        >
          <AttachedFilesSection
            ref="filesSection"
            :band-space-id="bandSpaceId"
            source-type="song"
            :source-id="song.id"
            :source-label="song.title"
            :can-attach="!readOnly"
            :title="t('band_space_setlist.song_page.rehearse')"
            :empty-title="t('band_space_setlist.song_page.files_empty_title')"
            :empty-hint="t('band_space_setlist.song_page.files_empty_hint')"
            feature-tab
            heading-tag="h2"
            heading-class="text-xs uppercase text-surface-500"
            :upload-on-mount="heldFiles"
            @count="fileCount = $event"
            @tab="tab = $event"
          />
        </section>

        <section v-show="!isBelowMd || phoneTab === 'lyrics'" v-bind="phonePanel('lyrics')" :class="CARD">
          <SongLyricsPanel v-if="!isBelowMd || lyricsOpened" :band-space-id="bandSpaceId" :song="song" :read-only="readOnly" @changed="reloadSong" />
        </section>

        <section v-if="isBelowMd" v-show="phoneTab === 'infos'" v-bind="phonePanel('infos')" :class="[CARD, 'flex flex-col gap-4']">
          <div>
            <h2 class="m-0 mb-1 text-xs font-semibold uppercase text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.song_drawer.notes') }}</h2>
            <InlineEditField
              :model-value="song.notes"
              :label="t('band_space_setlist.song_drawer.notes')"
              multiline
              :placeholder="t('band_space_setlist.song_drawer.notes_placeholder')"
              class="text-[0.9375rem]"
              :readonly="isLocked('notes')"
              :save="(value) => saveField('notes', value)"
            />
          </div>
          <div v-if="song.setlists?.length">
            <h2 class="m-0 mb-1 text-xs font-semibold uppercase text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.repertoire.column_setlists') }}</h2>
            <RouterLink
              v-for="setlist in song.setlists"
              :key="setlist.id"
              :to="setlistRoute(setlist.id)"
              class="flex min-h-12 items-center gap-3 border-t border-surface-200 text-[0.9375rem] text-surface-900 no-underline first-of-type:border-t-0 dark:border-surface-700 dark:text-surface-0"
            >
              <i class="pi pi-list text-surface-600 dark:text-surface-300" aria-hidden="true" />
              <span class="truncate">{{ setlist.name }}</span>
            </RouterLink>
          </div>
        </section>
      </div>
    </template>

    <div v-else class="flex flex-col gap-3">
      <Skeleton width="10rem" height="1rem" />
      <Skeleton width="18rem" height="2.25rem" />
      <Skeleton width="100%" height="12rem" border-radius="1rem" />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import bandSpaceSongsApi from '../../api/bandSpace/band-space-songs.js'
import AttachedFilesSection from '../../components/BandSpace/Files/AttachedFilesSection.vue'
import SongLyricsPanel from '../../components/BandSpace/Setlist/Lyrics/SongLyricsPanel.vue'
import InlineEditField from '../../components/BandSpace/Setlist/Song/InlineEditField.vue'
import PhoneBackBar from '../../components/Global/PhoneBackBar.vue'
import { useBandSpaceLiveRefresh } from '../../composables/useBandSpaceLiveRefresh.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { DANGER_MENU_PT } from '../../constants/dangerMenu.js'
import { useBandLyricsStore } from '../../store/bandSpace/bandSpaceLyrics.js'
import { useBandSongsStore } from '../../store/bandSpace/bandSpaceSongs.js'
import { recordOpenedItem } from '../../utils/openedItemRecorder.js'
import { takeSongFiles } from '../../utils/pendingSongFiles.js'
import { songPageWayBack } from '../../utils/songPage.js'
import { createSongSaveLock } from '../../utils/songSaveLock.js'
import { adjacentTabKey } from '../../utils/tabNavigation.js'
import { tabReaderRoute } from '../../utils/tabReaderBack.js'

/**
 * A song's page (#1431), which replaced its drawer: what the band rehearses with (the tab, the demos, the
 * documents) beside its lyrics, and its values edited where they are shown (#1067). Every way into a
 * song lands here: the répertoire, a setlist, Files, the palette, the activity feed.
 */
const FACTS = [
  {
    key: 'tonality',
    icon: 'pi-key',
    labelKey: 'band_space_setlist.song_drawer.tonality',
    placeholderKey: 'band_space_setlist.song_drawer.tonality_placeholder'
  },
  {
    key: 'tempo',
    icon: 'pi-stopwatch',
    labelKey: 'band_space_setlist.song_drawer.tempo',
    kind: 'number',
    unit: 'BPM'
  },
  {
    key: 'reference_duration',
    icon: 'pi-clock',
    labelKey: 'band_space_setlist.song_drawer.duration',
    kind: 'duration'
  }
]
const PHONE_TABS = [
  { key: 'rehearse', labelKey: 'band_space_setlist.song_page.tab_rehearse' },
  { key: 'lyrics', labelKey: 'band_space_setlist.song_page.tab_lyrics' },
  { key: 'infos', labelKey: 'band_space_setlist.song_page.tab_infos' }
]
const CARD =
  'rounded-2xl border border-surface-200 bg-surface-0 p-4 md:p-5 dark:border-surface-700 dark:bg-surface-900'
const VALUE_CHIP =
  'border border-surface-200 bg-surface-0 text-surface-900 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0'
// A missing value asks to be filled, in the amber of the répertoire's « + durée ».
const MISSING_CHIP =
  'border border-dashed border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const isBelowMd = useIsBelowMd()
const songsStore = useBandSongsStore()
const lyricsStore = useBandLyricsStore()
const filesSection = useTemplateRef('filesSection')
const menu = ref(null)

const bandSpaceId = computed(() => route.params.id)
const songId = computed(() => route.params.songId)
const song = ref(null)
const loadError = ref(null)
const fileCount = ref(0)
/** Files picked in « Nouveau morceau », which the files section sends as it opens. */
const heldFiles = ref([])
/** The section's featured Guitar Pro file, which the page offers to read from its head too. */
const tab = ref(null)
const phoneTab = ref('rehearse')
// A phone draws the lyrics once « Paroles » is opened, not hidden behind « Répéter ».
const lyricsOpened = ref(false)
watch(phoneTab, (tab) => {
  if (tab === 'lyrics') lyricsOpened.value = true
})

const readOnly = computed(() => song.value?.archive_datetime != null)
// An archived song shows the values it has: « + BPM » there would offer an edit it refuses.
const shownFacts = computed(() =>
  readOnly.value
    ? FACTS.filter((fact) => song.value[fact.key] !== null && song.value[fact.key] !== '')
    : FACTS
)

/** Where « back » leads, read from the history entry when the page opens: see songPageWayBack. */
const wayBack = ref({ returns: false, label: '' })
const backName = computed(() => wayBack.value.label || t('band_space_setlist.common.repertoire'))
const backAria = computed(() =>
  wayBack.value.label
    ? t('band_space_setlist.song_page.back_to', { name: wayBack.value.label })
    : t('band_space_setlist.song_page.back')
)

async function loadSong({ quiet = false } = {}) {
  // Another song can be opened (from the palette) while this one is still on its way.
  const id = songId.value
  try {
    const loaded = await bandSpaceSongsApi.getSong(bandSpaceId.value, id)
    if (id === songId.value) song.value = loaded
  } catch (e) {
    // A live refresh that fails keeps what is on screen.
    if (id === songId.value && (!quiet || !song.value)) loadError.value = e.message
  }
}

function reloadSong() {
  return loadSong({ quiet: true })
}

function load() {
  song.value = null
  loadError.value = null
  fileCount.value = 0
  tab.value = null
  heldFiles.value = takeSongFiles(songId.value)
  wayBack.value = songPageWayBack(window.history.state, (path) => router.resolve(path).name)
  phoneTab.value = 'rehearse'
  loadSong()
  // SongLyricsPanel finds the song's lyrics in the lyrics list.
  lyricsStore.fetchLyrics(bandSpaceId.value)
  recordOpenedItem(bandSpaceId.value, 'song', songId.value)
}

onMounted(load)
watch(songId, (id) => {
  if (id) load()
})

// The song and its lyrics change with the setlist module, its files with the file module.
useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['setlist'],
  refresh: () => {
    reloadSong()
    lyricsStore.fetchLyrics(bandSpaceId.value)
  }
})
useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['file'],
  refresh: () => filesSection.value?.reload()
})

// One field per PATCH, one PATCH at a time: see utils/songSaveLock.js.
const saveLock = createSongSaveLock(reactive(new Map()))

function isLocked(field) {
  return readOnly.value || saveLock.isLocked(song.value.id, field)
}

async function saveField(field, value) {
  const id = song.value.id
  const saved = await saveLock.run(id, field, () =>
    songsStore.updateSong(bandSpaceId.value, id, { [field]: value })
  )
  if (song.value?.id === id) song.value = saved
}

function setlistRoute(setlistId) {
  return {
    name: BAND_SPACE_ROUTES.SETLIST,
    params: { id: bandSpaceId.value },
    query: { setlist: setlistId }
  }
}

function goBack() {
  if (wayBack.value.returns) {
    router.back()
    return
  }
  router.push({
    name: BAND_SPACE_ROUTES.SETLIST,
    params: { id: bandSpaceId.value },
    query: isBelowMd.value ? { view: 'repertoire' } : {}
  })
}

function moveTab(step) {
  phoneTab.value = adjacentTabKey(PHONE_TABS, phoneTab.value, step)
  document.getElementById(`song-tab-${phoneTab.value}`)?.focus()
}

/** On a phone each part is its tab's panel; side by side on a wider screen, a plain section. */
function phonePanel(key) {
  return isBelowMd.value
    ? { id: `song-panel-${key}`, role: 'tabpanel', 'aria-labelledby': `song-tab-${key}` }
    : {}
}

function readTab() {
  router.push(tabReaderRoute(bandSpaceId.value, tab.value.id, song.value.title))
}

const menuItems = computed(() => [
  {
    label: t('band_space_setlist.common.archive'),
    icon: 'pi pi-archive',
    danger: true,
    command: confirmArchive
  }
])

function confirmArchive() {
  confirm.require({
    message: t('band_space_setlist.common.archive_song_confirm_message', {
      title: song.value.title
    }),
    header: t('band_space_setlist.common.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await songsStore.deleteSong(bandSpaceId.value, song.value.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.common.song_archived'),
          life: 3000
        })
        goBack()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_setlist.common.error'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}
</script>
