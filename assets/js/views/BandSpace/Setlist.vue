<template>
  <div>
    <div v-if="loadError" class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4">
      <Message severity="error" :closable="false">
        {{ loadError }}
      </Message>
      <Button :label="t('band_space_setlist.page.retry')" icon="pi pi-refresh" severity="secondary" @click="loadAll" />
    </div>

    <div v-else class="flex flex-col lg:flex-row gap-6">
      <aside class="hidden lg:block w-64 shrink-0 bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
        <SidebarContent
          :songs-count="songsStore.songs.length"
          :lyrics-count="lyricsStore.lyrics.length"
          :setlists="setlistsStore.setlists"
          :is-loading-setlists="setlistsStore.isLoading"
          :active-view="activeView"
          :active-setlist-id="activeSetlistId"
          :trash-count="trashCount"
          @select-repertoire="selectRepertoire"
          @select-lyrics="selectLyrics"
          @select-setlist="selectSetlist"
          @select-trash="selectTrash"
          @new-setlist="newSetlistDialogOpen = true"
        />
      </aside>

      <div v-if="!isBelowMd" class="lg:hidden bg-surface-0 dark:bg-surface-900 rounded-2xl p-3 border border-surface-200 dark:border-surface-700">
        <button
          type="button"
          class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-surface-50 dark:hover:bg-surface-800"
          @click="mobileNavOpen = true"
        >
          <i class="pi pi-bars text-surface-500"></i>
          <span class="flex-1 text-left truncate font-medium">{{ currentSelectionLabel }}</span>
          <i class="pi pi-chevron-down text-surface-400"></i>
        </button>
      </div>

      <Drawer v-model:visible="mobileNavOpen" position="left" :style="{ width: '20rem' }">
        <template #header>
          <span class="font-semibold">{{ t('band_space_setlist.page.mobile_nav_title') }}</span>
        </template>
        <SidebarContent
          :songs-count="songsStore.songs.length"
          :lyrics-count="lyricsStore.lyrics.length"
          :setlists="setlistsStore.setlists"
          :is-loading-setlists="setlistsStore.isLoading"
          :active-view="activeView"
          :active-setlist-id="activeSetlistId"
          :trash-count="trashCount"
          @select-repertoire="selectRepertoire"
          @select-lyrics="selectLyrics"
          @select-setlist="selectSetlist"
          @select-trash="selectTrash"
          @new-setlist="newSetlistDialogOpen = true"
        />
      </Drawer>

      <NewSetlistDialog
        v-model:visible="newSetlistDialogOpen"
        :band-space-id="bandSpaceId"
        @created="handleSetlistCreated"
      />

      <!-- A phone walks the module screen by screen, as the app does: home, then a setlist, the
           repertoire, the lyrics or the trash, each with its way back. -->
      <SetlistsHome
        v-if="isBelowMd && phoneScreen === 'home'"
        :band-space-id="bandSpaceId"
        :songs-count="songsStore.songs.length"
        :lyrics-count="lyricsStore.lyrics.length"
        :setlists="setlistsStore.setlists"
        :is-loading="setlistsStore.isLoading"
        :trash-count="trashCount"
        @select-repertoire="selectRepertoire"
        @select-lyrics="selectLyrics"
        @select-setlist="selectSetlist"
        @select-trash="selectTrash"
        @new-setlist="newSetlistDialogOpen = true"
      />

      <section v-else class="flex-1 min-w-0">
        <PhoneBackBar
          v-if="isBelowMd && (phoneScreen === 'lyrics' || phoneScreen === 'trash')"
          variant="page"
          class="mb-3"
          :back-label="t('band_space_setlist.phone.back_to_setlists')"
          :title="currentSelectionLabel"
          @back="goHome"
        />
        <RepertoireView
          v-if="activeView === 'repertoire'"
          :band-space-id="bandSpaceId"
          :focus-song-id="focusSongId"
          @back="goHome"
        />
        <LyricsView
          v-else-if="activeView === 'lyrics'"
          :band-space-id="bandSpaceId"
          :focus-lyrics-id="focusLyricsId"
        />
        <SetlistEditor
          v-else-if="activeView === 'setlist' && activeSetlistId"
          :band-space-id="bandSpaceId"
          :setlist-id="activeSetlistId"
          @archived="handleSetlistArchived"
          @duplicated="handleSetlistDuplicated"
          @back="goHome"
        />
        <SetlistTrashList
          v-else-if="activeView === 'trash'"
          :band-space-id="bandSpaceId"
          :setlists="[...setlistsStore.archivedSetlists]"
          :songs="[...songsStore.archivedSongs]"
          :lyrics="[...lyricsStore.archivedLyrics]"
          :is-loading="setlistsStore.isLoadingArchived || songsStore.isLoadingArchived || lyricsStore.isLoadingArchived"
        />
      </section>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Message from 'primevue/message'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import LyricsView from '../../components/BandSpace/Setlist/Lyrics/LyricsView.vue'
import NewSetlistDialog from '../../components/BandSpace/Setlist/NewSetlistDialog.vue'
import SetlistsHome from '../../components/BandSpace/Setlist/Phone/SetlistsHome.vue'
import RepertoireView from '../../components/BandSpace/Setlist/RepertoireView.vue'
import SetlistEditor from '../../components/BandSpace/Setlist/SetlistEditor.vue'
import SetlistTrashList from '../../components/BandSpace/Setlist/SetlistTrashList.vue'
import SidebarContent from '../../components/BandSpace/Setlist/SidebarContent.vue'
import PhoneBackBar from '../../components/Global/PhoneBackBar.vue'
import { useBandSpaceLiveRefresh } from '../../composables/useBandSpaceLiveRefresh.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { useBandLyricsStore } from '../../store/bandSpace/bandSpaceLyrics.js'
import { useBandSetlistsStore } from '../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../store/bandSpace/bandSpaceSongs.js'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const songsStore = useBandSongsStore()
const setlistsStore = useBandSetlistsStore()
const lyricsStore = useBandLyricsStore()

// Wipe any previous space's data synchronously before first render.
songsStore.clear()
setlistsStore.clear()
lyricsStore.clear()

const bandSpaceId = computed(() => route.params.id)

const activeView = ref('repertoire') // 'repertoire' | 'lyrics' | 'setlist' | 'trash'
const isBelowMd = useIsBelowMd()
const activeSetlistId = ref(null)
const mobileNavOpen = ref(false)
const newSetlistDialogOpen = ref(false)

const activeSetlist = computed(() =>
  activeSetlistId.value
    ? (setlistsStore.setlists.find((s) => s.id === activeSetlistId.value) ?? null)
    : null
)

/**
 * Set by the command palette, which links to a song rather than to the repertoire as a whole. No
 * branch is needed in syncFromQuery(): a query carrying only ?song= already falls through to the
 * repertoire, which is the view holding the songs.
 */
const focusSongId = computed(() => (typeof route.query.song === 'string' ? route.query.song : null))
const focusLyricsId = computed(() =>
  typeof route.query.lyrics === 'string' ? route.query.lyrics : null
)

const loadError = computed(
  () => songsStore.loadError || setlistsStore.loadError || lyricsStore.loadError
)

const trashCount = computed(
  () =>
    setlistsStore.archivedSetlists.length +
    songsStore.archivedSongs.length +
    lyricsStore.archivedLyrics.length
)

const currentSelectionLabel = computed(() => {
  if (activeView.value === 'trash') return t('band_space_setlist.sidebar.trash')
  if (activeView.value === 'lyrics') return t('band_space_setlist.sidebar.lyrics')
  if (activeView.value === 'setlist' && activeSetlist.value) return activeSetlist.value.name
  return t('band_space_setlist.common.repertoire')
})

// A phone walks into a screen, so its back gesture returns to the one before; the desktop swaps
// views in place, as it always has.
function selectView(view, query, setlistId = null) {
  activeView.value = view
  activeSetlistId.value = setlistId
  if (isBelowMd.value) router.push({ query })
  else router.replace({ query })
  mobileNavOpen.value = false
}

// A phone opens on its home, so the repertoire needs a name in the query there; the desktop keeps
// it as the view an empty query shows.
const selectRepertoire = () =>
  selectView('repertoire', isBelowMd.value ? { view: 'repertoire' } : {})
const selectLyrics = () => selectView('lyrics', { view: 'lyrics' })
const selectSetlist = (id) => selectView('setlist', { setlist: id }, id)
const selectTrash = () => selectView('trash', { view: 'trash' })

/**
 * The phone's screen: the same query as the desktop's views, read for the screen to show. An empty
 * query is the home there, where the desktop shows the repertoire; a palette link to a song (?song=)
 * still lands in the repertoire, which opens it.
 */
const phoneScreen = computed(() => {
  if (activeView.value === 'setlist') return 'setlist'
  if (activeView.value === 'lyrics' || activeView.value === 'trash') return activeView.value
  if (route.query.view === 'repertoire' || focusSongId.value) return 'repertoire'
  return 'home'
})

// Back to the phone's home: the page before when the home led here, the home itself otherwise (a
// link straight into a setlist).
function goHome() {
  if (window.history.state?.back) router.back()
  else router.replace({ query: {} })
}

function syncFromQuery() {
  const setlistParam = route.query.setlist
  if (typeof setlistParam === 'string' && setlistParam) {
    activeView.value = 'setlist'
    activeSetlistId.value = setlistParam
    return
  }
  if (route.query.view === 'trash') {
    activeView.value = 'trash'
    activeSetlistId.value = null
    return
  }
  if (route.query.view === 'lyrics') {
    activeView.value = 'lyrics'
    activeSetlistId.value = null
    return
  }
  activeView.value = 'repertoire'
  activeSetlistId.value = null
}

function loadAll() {
  if (!bandSpaceId.value) return
  songsStore.fetchSongs(bandSpaceId.value)
  setlistsStore.fetchSetlists(bandSpaceId.value)
  // The trash is loaded up front rather than on first open, because its count sits in the sidebar
  // next to the live lists. Both collections are unpaginated and hold a handful of rows.
  songsStore.fetchArchivedSongs(bandSpaceId.value)
  setlistsStore.fetchArchivedSetlists(bandSpaceId.value)
  // Loaded with the rest even when « Paroles » is closed: a song's drawer finds its lyrics in it.
  lyricsStore.fetchLyrics(bandSpaceId.value)
  lyricsStore.fetchArchivedLyrics(bandSpaceId.value)
}

// The song, lyrics and item drawers keep the copy they were opened with, and the editor does not rebuild
// its list while an item is being dragged, so a refetch never moves anything under the member.
useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['setlist'],
  refresh: () => {
    loadAll()
    if (activeSetlistId.value) {
      setlistsStore.fetchActive(bandSpaceId.value, activeSetlistId.value, { quiet: true })
    }
  }
})

function handleSetlistCreated(created) {
  selectSetlist(created.id)
}

function handleSetlistArchived() {
  if (isBelowMd.value) goHome()
  else selectRepertoire()
}

function handleSetlistDuplicated(newId) {
  // duplicateSetlist already inserted the copy into the store's setlists[],
  // so we can navigate directly without a refetch.
  selectSetlist(newId)
}

onMounted(() => {
  syncFromQuery()
  loadAll()
})

onUnmounted(() => {
  songsStore.clear()
  setlistsStore.clear()
  lyricsStore.clear()
})

watch(
  () => route.query,
  () => syncFromQuery()
)
</script>
