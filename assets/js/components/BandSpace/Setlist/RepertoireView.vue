<template>
  <div class="flex flex-col gap-4">
    <!-- A phone: the songs as a list under a back bar, the selection switched on from that bar. -->
    <div v-if="isBelowMd" class="flex flex-col" :class="selected.size > 0 ? 'pb-40' : 'pb-24'">
      <PhoneBackBar
        variant="page"
        class="mb-2"
        :back-label="t('band_space_setlist.phone.back_to_setlists')"
        :title="t('band_space_setlist.common.repertoire')"
        @back="emit('back')"
      >
        <template #actions>
          <Button
            :icon="selecting ? 'pi pi-times' : 'pi pi-check-square'"
            text
            rounded
            severity="secondary"
            :aria-label="selecting ? t('band_space_setlist.repertoire.clear_selection') : t('band_space_setlist.phone.select')"
            :aria-pressed="selecting"
            @click="toggleSelecting"
          />
        </template>
      </PhoneBackBar>
      <div class="-mx-2 flex flex-col">
        <IconField icon-position="left" class="mb-2">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="query"
            type="search"
            :placeholder="t('band_space_setlist.common.search_song_placeholder')"
            :aria-label="t('band_space_setlist.common.search_repertoire_aria')"
            class="h-11 w-full !rounded-xl"
          />
        </IconField>
        <div class="mb-3 flex gap-2 overflow-x-auto [scrollbar-width:none]">
          <button
            type="button"
            :class="[PHONE_CHIP, withoutLyrics ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
            :aria-pressed="withoutLyrics"
            @click="withoutLyrics = !withoutLyrics"
          >
            {{ t('band_space_setlist.repertoire.filter_without_lyrics', { count: counts.withoutLyrics }) }}
          </button>
          <button
            type="button"
            :class="[PHONE_CHIP, missingInfo ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
            :aria-pressed="missingInfo"
            @click="missingInfo = !missingInfo"
          >
            {{ t('band_space_setlist.repertoire.filter_missing_info', { count: counts.missingInfo }) }}
          </button>
        </div>

        <div v-if="songsStore.isLoading && songsStore.songs.length === 0" class="flex flex-col gap-2">
          <Skeleton v-for="i in 4" :key="i" width="100%" height="3.75rem" border-radius="1rem" />
        </div>
        <p v-else-if="visibleSongs.length === 0" class="p-8 text-center text-surface-600 dark:text-surface-300">
          {{ songsStore.songs.length > 0 ? t('band_space_setlist.common.no_matching_song') : t('band_space_setlist.repertoire.empty') }}
        </p>
        <ul
          v-else
          class="m-0 list-none divide-y divide-surface-200 overflow-hidden rounded-2xl border border-surface-200 bg-surface-0 p-0 dark:divide-surface-700 dark:border-surface-700 dark:bg-surface-900"
        >
          <li
            v-for="song in visibleSongs"
            :key="song.id"
            class="flex min-h-15 items-center gap-2.5 py-1.5 pr-1 pl-4"
            :class="{ 'bg-primary-50 dark:bg-primary-900/30': selected.has(song.id) }"
          >
            <Checkbox
              v-if="selecting"
              :model-value="selected.has(song.id)"
              :binary="true"
              :aria-label="t('band_space_setlist.repertoire.select_song_aria', { title: song.title })"
              @update:model-value="toggleSelected(song.id)"
            />
            <button
              type="button"
              class="flex min-w-0 flex-1 flex-col gap-0.5 text-left"
              @click="selecting ? toggleSelected(song.id) : openSong(song)"
            >
              <span class="flex items-center gap-2 font-medium">
                <span class="truncate">{{ song.title }}</span>
                <i
                  v-if="song.has_lyrics"
                  class="pi pi-align-left shrink-0 text-xs text-primary"
                  role="img"
                  :aria-label="t('band_space_setlist.common.lyrics_present')"
                />
              </span>
              <span class="text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ songFacts(song) }}</span>
            </button>
            <button
              v-if="!song.reference_duration"
              type="button"
              class="shrink-0 rounded-full border border-dashed border-amber-500 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-300"
              :aria-label="t('band_space_setlist.common.add_duration_aria', { title: song.title })"
              @click="promptDuration($event, song)"
            >
              {{ t('band_space_setlist.common.add_duration') }}
            </button>
            <span v-else class="shrink-0 text-[0.9375rem] tabular-nums">{{ formatDuration(song.reference_duration) }}</span>
            <Button
              icon="pi pi-ellipsis-v"
              severity="secondary"
              text
              rounded
              class="h-11! w-10! shrink-0"
              aria-haspopup="dialog"
              :aria-label="t('band_space_setlist.repertoire.song_actions_aria', { title: song.title })"
              @click="openSongSheet(song)"
            />
          </li>
        </ul>

        <Button
          v-if="selected.size === 0"
          :label="t('band_space_setlist.repertoire.add_song')"
          icon="pi pi-plus"
          rounded
          class="fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
          @click="openCreateDialog"
        />

        <BottomSheet
          v-model:visible="isSongSheetOpen"
          :title="menuTargetSong?.title ?? ''"
          @after-hide="songSheet.onAfterHide"
        >
          <SheetAction icon="pi-pencil" @click="songSheet.run(() => openSong(menuTargetSong))">
            {{ t('band_space_setlist.common.edit') }}
          </SheetAction>
          <SheetAction icon="pi-archive" danger @click="songSheet.run(() => confirmArchive(menuTargetSong))">
            {{ t('band_space_setlist.common.archive') }}
          </SheetAction>
        </BottomSheet>
      </div>
    </div>

    <template v-else>
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700 flex flex-wrap items-center gap-2">
      <div class="flex-1 min-w-[200px]">
        <IconField iconPosition="left">
          <InputIcon class="pi pi-search" />
          <InputText v-model="query" type="search" :placeholder="t('band_space_setlist.common.search_song_placeholder')" :aria-label="t('band_space_setlist.common.search_repertoire_aria')" class="w-full" />
        </IconField>
      </div>
      <Button
        :label="t('band_space_setlist.repertoire.filter_without_lyrics', { count: counts.withoutLyrics })"
        size="small"
        severity="secondary"
        :outlined="!withoutLyrics"
        :aria-pressed="withoutLyrics"
        @click="withoutLyrics = !withoutLyrics"
      />
      <Button
        :label="t('band_space_setlist.repertoire.filter_missing_info', { count: counts.missingInfo })"
        size="small"
        severity="secondary"
        :outlined="!missingInfo"
        :aria-pressed="missingInfo"
        v-tooltip.top="t('band_space_setlist.repertoire.filter_missing_info_tooltip')"
        @click="missingInfo = !missingInfo"
      />
      <Button :label="t('band_space_setlist.repertoire.add_song')" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden">
      <div v-if="songsStore.isLoading && songsStore.songs.length === 0" class="p-4 flex flex-col gap-2">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>
      <div v-else-if="visibleSongs.length === 0" class="p-8 text-center text-surface-600 dark:text-surface-300">
        <i class="pi pi-headphones text-3xl mb-3 block" aria-hidden="true"></i>
        {{ songsStore.songs.length > 0 ? t('band_space_setlist.common.no_matching_song') : t('band_space_setlist.repertoire.empty') }}
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-surface-50 dark:bg-surface-800 text-xs uppercase tracking-wide text-surface-600 dark:text-surface-300">
          <tr>
            <th class="w-10 pl-4 py-3">
              <Checkbox
                :model-value="allVisibleSelected"
                :indeterminate="someVisibleSelected && !allVisibleSelected"
                :binary="true"
                :aria-label="t('band_space_setlist.repertoire.select_all_aria')"
                @update:model-value="toggleAllVisible"
              />
            </th>
            <th class="text-left px-3 py-3">{{ t('band_space_setlist.repertoire.column_title') }}</th>
            <th class="text-left px-3 py-3 hidden md:table-cell">{{ t('band_space_setlist.repertoire.column_tonality') }}</th>
            <th class="text-right px-3 py-3 hidden md:table-cell">{{ t('band_space_setlist.repertoire.column_tempo') }}</th>
            <th class="text-right px-3 py-3">{{ t('band_space_setlist.repertoire.column_duration') }}</th>
            <th class="text-left px-3 py-3 hidden lg:table-cell">{{ t('band_space_setlist.repertoire.column_content') }}</th>
            <th class="text-left px-3 py-3 hidden lg:table-cell">{{ t('band_space_setlist.repertoire.column_setlists') }}</th>
            <th class="px-3 py-3 w-12"><span class="sr-only">{{ t('band_space_setlist.repertoire.column_actions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="song in visibleSongs"
            :key="song.id"
            :class="[
              'border-t border-surface-100 dark:border-surface-800',
              selected.has(song.id) ? 'bg-primary-50 dark:bg-primary-900/30' : 'hover:bg-surface-50 dark:hover:bg-surface-800'
            ]"
          >
            <td class="pl-4 py-2.5">
              <Checkbox
                :model-value="selected.has(song.id)"
                :binary="true"
                :aria-label="t('band_space_setlist.repertoire.select_song_aria', { title: song.title })"
                @update:model-value="toggleSelected(song.id)"
              />
            </td>
            <td class="px-3 py-2.5 font-medium">
              <!-- A real link: a middle click opens the song's page in a tab. -->
              <RouterLink :to="songPageRoute(bandSpaceId, song.id)" class="text-left text-surface-900 no-underline hover:underline dark:text-surface-0">{{ song.title }}</RouterLink>
            </td>
            <!-- Tonality, BPM and duration are edited in their cell; a phone edits them on the song's page. -->
            <td v-for="cell in CELLS" :key="cell.key" :class="['px-3 py-2 tabular-nums', cell.cellClass]">
              <InlineEditField
                :model-value="song[cell.key]"
                :label="t('band_space_setlist.repertoire.cell_label', { field: t(cell.labelKey), title: song.title })"
                :kind="cell.kind"
                inline
                input-class="!w-24"
                :readonly="isCellLocked(song, cell.key)"
                :save="(value) => saveCell(song, cell.key, value)"
              >
                <template #default="{ display, isEmpty }">
                  <span v-if="isEmpty" :class="MISSING_CELL">{{ t(cell.missingKey) }}</span>
                  <template v-else>{{ display }}</template>
                </template>
              </InlineEditField>
            </td>
            <td class="px-3 py-2.5 hidden lg:table-cell">
              <span class="flex items-center gap-2.5">
                <i
                  :class="['pi pi-align-left text-sm', song.has_lyrics ? 'text-primary' : 'text-surface-400 dark:text-surface-500']"
                  v-tooltip.top="song.has_lyrics ? t('band_space_setlist.common.lyrics_present') : t('band_space_setlist.repertoire.lyrics_missing')"
                  :aria-label="song.has_lyrics ? t('band_space_setlist.common.lyrics_present') : t('band_space_setlist.repertoire.lyrics_missing')"
                />
                <span
                  v-if="songFileCounts.get(song.id)"
                  class="flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300"
                  :aria-label="t('band_space_setlist.common.file_count_aria', songFileCounts.get(song.id))"
                >
                  <i class="pi pi-paperclip text-xs" aria-hidden="true" />{{ songFileCounts.get(song.id) }}
                </span>
              </span>
            </td>
            <td class="px-3 py-2.5 hidden lg:table-cell">
              <span v-if="song.setlists?.length" class="flex flex-wrap gap-1">
                <button
                  v-for="setlist in song.setlists"
                  :key="setlist.id"
                  type="button"
                  class="text-xs px-2 py-0.5 rounded-full bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200 hover:bg-surface-200 dark:hover:bg-surface-700"
                  :aria-label="t('band_space_setlist.repertoire.open_setlist_aria', { name: setlist.name })"
                  @click="openSetlist(setlist.id)"
                >
                  {{ setlist.name }}
                </button>
              </span>
              <span v-else class="text-xs italic text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.repertoire.never_played') }}</span>
            </td>
            <td class="px-3 py-2.5 text-right">
              <Button
                icon="pi pi-ellipsis-v"
                severity="secondary"
                text
                rounded
                :aria-label="t('band_space_setlist.repertoire.song_actions_aria', { title: song.title })"
                @click="openMenu($event, song)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>

    <!-- Held at the bottom of the screen while there is a selection, so its actions stay in reach
         however far down the table the member scrolled. -->
    <div
      v-if="selected.size > 0"
      class="sticky bottom-4 z-20 self-center flex flex-wrap items-center gap-2 px-4 py-2.5 rounded-2xl shadow-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 max-md:fixed max-md:inset-x-4 max-md:bottom-[calc(1rem+env(safe-area-inset-bottom))] max-md:justify-center"
      role="region"
      :aria-label="t('band_space_setlist.repertoire.selection_aria')"
    >
      <span class="text-sm font-medium mr-1" aria-live="polite">
        {{ t('band_space_setlist.repertoire.selection_count', selected.size) }}
      </span>
      <Button
        :label="t('band_space_setlist.repertoire.add_to_setlist')"
        icon="pi pi-chevron-down"
        icon-pos="right"
        size="small"
        aria-haspopup="menu"
        aria-controls="repertoire-add-to-setlist"
        :disabled="setlistsStore.setlists.length === 0 || isWorking"
        @click="addToMenu?.toggle($event)"
      />
      <Button
        :label="t('band_space_setlist.repertoire.new_setlist_with_selection')"
        size="small"
        severity="secondary"
        :disabled="isWorking"
        @click="newSetlistOpen = true"
      />
      <Button :label="t('band_space_setlist.common.archive')" size="small" severity="danger" outlined :disabled="isWorking" @click="confirmArchiveSelection" />
      <!-- A phone clears the selection from the × in its bar. -->
      <Button
        icon="pi pi-times"
        size="small"
        severity="secondary"
        text
        rounded
        class="max-md:!hidden"
        :aria-label="t('band_space_setlist.repertoire.clear_selection')"
        @click="clearSelection"
      />
      <Menu id="repertoire-add-to-setlist" ref="addToMenu" :model="addToMenuItems" :popup="true" />
    </div>

    <Menu ref="actionsMenu" :model="menuItems" :popup="true" />

    <DurationPrompt ref="durationPrompt" :label="durationLabel" :saving="isSavingDuration" @submit="saveDuration" />

    <NewSetlistDialog v-model:visible="newSetlistOpen" :band-space-id="bandSpaceId" @created="fillNewSetlist" />

    <NewSongDialog v-model:visible="newSongOpen" :band-space-id="bandSpaceId" @created="openSong" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, reactive, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useSongFileCounts } from '../../../composables/useSongFileCounts.js'
import { useUrlFilters } from '../../../composables/useUrlFilters.js'
import { PHONE_CHIP, PHONE_CHIP_OFF, PHONE_CHIP_ON } from '../../../constants/phoneControls.js'
import { lastRestoredScrollPosition } from '../../../router/scrollPosition.js'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { filterCounts, filterSongs } from '../../../utils/repertoireFilters.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import { songPageRoute } from '../../../utils/songPage.js'
import { createSongSaveLock } from '../../../utils/songSaveLock.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import PhoneBackBar from '../../Global/PhoneBackBar.vue'
import SheetAction from '../../Global/SheetAction.vue'
import DurationPrompt from './Editor/DurationPrompt.vue'
import NewSetlistDialog from './NewSetlistDialog.vue'
import NewSongDialog from './NewSongDialog.vue'
import InlineEditField from './Song/InlineEditField.vue'

/**
 * The band's songs as a table (#1063): what each one is missing, which setlists play it, and a
 * selection to add several to a setlist, start one from them, or archive them.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

/** The cells a song's values are edited in, from tablet width up; a phone edits them on the song's page. */
const CELLS = [
  {
    key: 'tonality',
    labelKey: 'band_space_setlist.song_drawer.tonality',
    missingKey: 'band_space_setlist.repertoire.add_tonality',
    cellClass: 'hidden md:table-cell'
  },
  {
    key: 'tempo',
    labelKey: 'band_space_setlist.song_drawer.tempo',
    missingKey: 'band_space_setlist.repertoire.add_tempo',
    kind: 'number',
    cellClass: 'hidden md:table-cell text-right'
  },
  {
    key: 'reference_duration',
    labelKey: 'band_space_setlist.song_drawer.duration',
    missingKey: 'band_space_setlist.common.add_duration',
    kind: 'duration',
    cellClass: 'text-right'
  }
]
const MISSING_CELL =
  'inline-flex h-7 items-center rounded-md border border-dashed border-amber-500 bg-amber-50 px-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'

const emit = defineEmits(['back'])
const { t } = useI18n()
const isBelowMd = useIsBelowMd()
const route = useRoute()
const router = useRouter()
const songsStore = useBandSongsStore()
const setlistsStore = useBandSetlistsStore()
const confirm = useConfirm()
const toast = useToast()

// The search and the filters live in the URL, so Back from a song's page finds the list as it was left.
const { filters } = useUrlFilters(
  { q: '', no_lyrics: false, missing_info: false },
  { preserveKeys: ['view'] }
)
const query = toRef(filters, 'q')
const withoutLyrics = toRef(filters, 'no_lyrics')
const missingInfo = toRef(filters, 'missing_info')
const newSongOpen = ref(false)
const actionsMenu = ref(null)
const menuTargetSong = ref(null)

function showError(e) {
  toast.add({
    severity: 'error',
    summary: t('band_space_setlist.common.error'),
    detail: e.message,
    life: 5000
  })
}

// Read on mount: a song's files change on its page, and coming back from it mounts the list again.
const { counts: songFileCounts, load: loadSongsWithFiles } = useSongFileCounts(
  () => props.bandSpaceId
)

// Also re-read the songs: the setlists each one is in move whenever a setlist is edited, and the
// member usually comes back here from one.
onMounted(async () => {
  await Promise.allSettled([loadSongsWithFiles(), songsStore.fetchSongs(props.bandSpaceId)])
  restoreScroll()
})

// Back from a song's page: the router restores the scroll before the list is drawn, when the page is still
// too short to reach it. The list puts it back once the songs and their file counts are both on screen,
// since the counts change the rows' height and would carry the browser's scroll anchoring away.
async function restoreScroll() {
  const position = lastRestoredScrollPosition()
  if (!position) return
  await nextTick()
  window.scrollTo({ top: position.top ?? 0, left: position.left ?? 0 })
}

const counts = computed(() => filterCounts(songsStore.songs))
const visibleSongs = computed(() =>
  filterSongs(songsStore.songs, {
    query: query.value,
    withoutLyrics: withoutLyrics.value,
    missingInfo: missingInfo.value
  })
)

// --- Selection -----------------------------------------------------------------------------------

const selected = ref(new Set())

// A song that left the repertoire (archived here or elsewhere) leaves the selection too.
watch(
  () => songsStore.songs,
  (songs) => {
    const ids = new Set(songs.map((song) => song.id))
    const kept = [...selected.value].filter((id) => ids.has(id))
    if (kept.length !== selected.value.size) selected.value = new Set(kept)
  }
)

const allVisibleSelected = computed(
  () =>
    visibleSongs.value.length > 0 && visibleSongs.value.every((song) => selected.value.has(song.id))
)
const someVisibleSelected = computed(() =>
  visibleSongs.value.some((song) => selected.value.has(song.id))
)

function toggleSelected(id) {
  const next = new Set(selected.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selected.value = next
}

function toggleAllVisible() {
  const next = new Set(selected.value)
  const select = !allVisibleSelected.value
  for (const song of visibleSongs.value) {
    if (select) next.add(song.id)
    else next.delete(song.id)
  }
  selected.value = next
}

function clearSelection() {
  selected.value = new Set()
  selecting.value = false
}

// A phone shows the tick boxes only once asked for, from the bar: the rows open their song otherwise.
const selecting = ref(false)

// A selection made on a wider screen stays in reach after a resize: its tick boxes come with it.
watch(isBelowMd, (phone) => {
  if (phone && selected.value.size > 0) selecting.value = true
})

function toggleSelecting() {
  if (selecting.value) clearSelection()
  else selecting.value = true
}

// « Am · 112 BPM », what a phone row says under its title.
function songFacts(song) {
  return [
    song.tonality,
    song.tempo ? t('band_space_setlist.common.tempo_bpm', { tempo: song.tempo }) : null
  ]
    .filter(Boolean)
    .join(' · ')
}

/** In repertoire order, whatever order they were ticked in: a setlist built from them reads alphabetically. */
function selectedSongIds() {
  return songsStore.songs.filter((song) => selected.value.has(song.id)).map((song) => song.id)
}

const isWorking = ref(false)
const addToMenu = ref(null)
const newSetlistOpen = ref(false)

const addToMenuItems = computed(() =>
  setlistsStore.setlists.map((setlist) => ({
    label: setlist.name,
    command: () => addSelectionTo(setlist)
  }))
)

async function addSelectionTo(setlist) {
  const ids = selectedSongIds()
  isWorking.value = true
  try {
    await setlistsStore.addSongs(props.bandSpaceId, setlist.id, ids)
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.repertoire.songs_added', { name: setlist.name }, ids.length),
      life: 3000
    })
    clearSelection()
    await songsStore.fetchSongs(props.bandSpaceId)
  } catch (e) {
    showError(e)
  } finally {
    isWorking.value = false
  }
}

/**
 * Two requests, the setlist then its songs. Should the second fail, the setlist is there and empty,
 * which its empty state already knows how to fill, so it is opened either way.
 */
async function fillNewSetlist(created) {
  const ids = selectedSongIds()
  isWorking.value = true
  try {
    await setlistsStore.addSongs(props.bandSpaceId, created.id, ids)
    clearSelection()
  } catch (e) {
    showError(e)
  } finally {
    isWorking.value = false
    openSetlist(created.id)
  }
}

function confirmArchiveSelection() {
  const ids = selectedSongIds()
  confirm.require({
    message: t('band_space_setlist.repertoire.archive_selection_confirm_message', ids.length),
    header: t('band_space_setlist.common.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      isWorking.value = true
      try {
        await songsStore.archiveSongs(props.bandSpaceId, ids)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.repertoire.songs_archived', ids.length),
          life: 3000
        })
        clearSelection()
      } catch (e) {
        showError(e)
      } finally {
        isWorking.value = false
      }
    }
  })
}

function openSetlist(setlistId) {
  router.replace({ query: { setlist: setlistId } })
}

// --- Duration in place ---------------------------------------------------------------------------

const durationPrompt = ref(null)
const durationSong = ref(null)
const isSavingDuration = ref(false)
const durationLabel = computed(() =>
  t('band_space_setlist.common.duration_of_song', { title: durationSong.value?.title ?? '' })
)

function promptDuration(event, song) {
  durationSong.value = song
  durationPrompt.value?.open(event)
}

async function saveDuration(seconds) {
  if (!durationSong.value || seconds === null) return
  isSavingDuration.value = true
  try {
    await songsStore.updateSong(props.bandSpaceId, durationSong.value.id, {
      reference_duration: seconds
    })
    durationPrompt.value?.hide()
  } catch (e) {
    showError(e)
  } finally {
    isSavingDuration.value = false
  }
}

// --- Values in place -------------------------------------------------------------------------------

// One field per PATCH, one PATCH at a time per song: see utils/songSaveLock.js.
const cellLock = createSongSaveLock(reactive(new Map()))

function isCellLocked(song, field) {
  return cellLock.isLocked(song.id, field)
}

function saveCell(song, field, value) {
  return cellLock.run(song.id, field, () =>
    songsStore.updateSong(props.bandSpaceId, song.id, { [field]: value })
  )
}

// --- One song ------------------------------------------------------------------------------------

const menuItems = computed(() => [
  {
    label: t('band_space_setlist.common.edit'),
    icon: 'pi pi-pencil',
    command: () => openSong(menuTargetSong.value)
  },
  {
    label: t('band_space_setlist.common.archive'),
    icon: 'pi pi-archive',
    command: () => confirmArchive(menuTargetSong.value)
  }
])

function openCreateDialog() {
  newSongOpen.value = true
}

function openSong(song) {
  if (song) router.push(songPageRoute(props.bandSpaceId, song.id))
}

// On a phone the row's actions open in a sheet; « Modifier » opens the song's page and « Archiver » a
// confirm, so both wait for the sheet to be gone.
const isSongSheetOpen = ref(false)

const songSheet = useActionAfterSheetHide(isSongSheetOpen)

function openSongSheet(song) {
  menuTargetSong.value = song
  isSongSheetOpen.value = true
}

function openMenu(event, song) {
  menuTargetSong.value = song
  actionsMenu.value?.toggle(event)
}

function confirmArchive(song) {
  if (!song) return
  confirm.require({
    message: t('band_space_setlist.common.archive_song_confirm_message', { title: song.title }),
    header: t('band_space_setlist.common.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await songsStore.deleteSong(props.bandSpaceId, song.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.common.song_archived'),
          life: 3000
        })
      } catch (e) {
        showError(e)
      }
    }
  })
}
</script>
