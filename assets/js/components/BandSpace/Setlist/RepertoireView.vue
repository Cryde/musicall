<template>
  <div class="flex flex-col gap-4">
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700 flex flex-wrap items-center gap-2">
      <div class="flex-1 min-w-[200px]">
        <IconField iconPosition="left">
          <InputIcon class="pi pi-search" />
          <InputText v-model="query" type="search" placeholder="Rechercher un titre…" aria-label="Rechercher dans le répertoire" class="w-full" />
        </IconField>
      </div>
      <Button
        :label="`Sans paroles · ${counts.withoutLyrics}`"
        size="small"
        severity="secondary"
        :outlined="!withoutLyrics"
        :aria-pressed="withoutLyrics"
        @click="withoutLyrics = !withoutLyrics"
      />
      <Button
        :label="`Infos manquantes · ${counts.missingInfo}`"
        size="small"
        severity="secondary"
        :outlined="!missingInfo"
        :aria-pressed="missingInfo"
        v-tooltip.top="'Sans tonalité, BPM ou durée'"
        @click="missingInfo = !missingInfo"
      />
      <Button label="Ajouter un titre" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden">
      <div v-if="songsStore.isLoading && songsStore.songs.length === 0" class="p-4 flex flex-col gap-2">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>
      <div v-else-if="visibleSongs.length === 0" class="p-8 text-center text-surface-600 dark:text-surface-300">
        <i class="pi pi-headphones text-3xl mb-3 block" aria-hidden="true"></i>
        {{ songsStore.songs.length > 0 ? 'Aucun titre ne correspond.' : 'Aucun titre dans le répertoire. Commencez par en ajouter un.' }}
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-surface-50 dark:bg-surface-800 text-xs uppercase tracking-wide text-surface-600 dark:text-surface-300">
          <tr>
            <th class="w-10 pl-4 py-3">
              <Checkbox
                :model-value="allVisibleSelected"
                :indeterminate="someVisibleSelected && !allVisibleSelected"
                :binary="true"
                aria-label="Sélectionner tous les titres affichés"
                @update:model-value="toggleAllVisible"
              />
            </th>
            <th class="text-left px-3 py-3">Titre</th>
            <th class="text-left px-3 py-3 hidden md:table-cell">Tonalité</th>
            <th class="text-right px-3 py-3 hidden md:table-cell">BPM</th>
            <th class="text-right px-3 py-3">Durée</th>
            <th class="text-left px-3 py-3 hidden lg:table-cell">Contenu</th>
            <th class="text-left px-3 py-3 hidden lg:table-cell">Dans les setlists</th>
            <th class="px-3 py-3 w-12"><span class="sr-only">Actions</span></th>
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
                :aria-label="`Sélectionner ${song.title}`"
                @update:model-value="toggleSelected(song.id)"
              />
            </td>
            <td class="px-3 py-2.5 font-medium">
              <button type="button" class="text-left hover:underline" @click="openDrawer(song)">{{ song.title }}</button>
            </td>
            <td class="px-3 py-2.5 hidden md:table-cell" :class="song.tonality ? '' : 'text-surface-500 dark:text-surface-400'">
              {{ song.tonality || '—' }}
            </td>
            <td class="px-3 py-2.5 hidden md:table-cell text-right tabular-nums" :class="song.tempo ? '' : 'text-surface-500 dark:text-surface-400'">
              {{ song.tempo || '—' }}
            </td>
            <td class="px-3 py-2.5 text-right tabular-nums">
              <button
                v-if="!song.reference_duration"
                type="button"
                class="h-7 w-16 rounded-md border border-dashed border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs"
                :aria-label="`Ajouter la durée de ${song.title}`"
                @click="promptDuration($event, song)"
              >
                + durée
              </button>
              <template v-else>{{ formatDuration(song.reference_duration) }}</template>
            </td>
            <td class="px-3 py-2.5 hidden lg:table-cell">
              <span class="flex items-center gap-2.5">
                <i
                  :class="['pi pi-align-left text-sm', song.has_lyrics ? 'text-primary' : 'text-surface-400 dark:text-surface-500']"
                  v-tooltip.top="song.has_lyrics ? 'Paroles et accords' : 'Pas encore de paroles'"
                  :aria-label="song.has_lyrics ? 'Paroles et accords' : 'Pas encore de paroles'"
                />
                <span
                  v-if="songFileCounts.get(song.id)"
                  class="flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300"
                  :aria-label="`${songFileCounts.get(song.id)} ${songFileCounts.get(song.id) > 1 ? 'fichiers' : 'fichier'}`"
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
                  :aria-label="`Ouvrir la setlist ${setlist.name}`"
                  @click="openSetlist(setlist.id)"
                >
                  {{ setlist.name }}
                </button>
              </span>
              <span v-else class="text-xs italic text-surface-600 dark:text-surface-300">Jamais joué</span>
            </td>
            <td class="px-3 py-2.5 text-right">
              <Button
                icon="pi pi-ellipsis-v"
                severity="secondary"
                text
                rounded
                :aria-label="`Actions pour ${song.title}`"
                @click="openMenu($event, song)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Held at the bottom of the screen while there is a selection, so its actions stay in reach
         however far down the table the member scrolled. -->
    <div
      v-if="selected.size > 0"
      class="sticky bottom-4 z-20 self-center flex flex-wrap items-center gap-2 px-4 py-2.5 rounded-2xl shadow-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700"
      role="region"
      aria-label="Actions sur la sélection"
    >
      <span class="text-sm font-medium mr-1" aria-live="polite">
        {{ selected.size }} {{ selected.size > 1 ? 'titres sélectionnés' : 'titre sélectionné' }}
      </span>
      <Button
        label="Ajouter à une setlist"
        icon="pi pi-chevron-down"
        icon-pos="right"
        size="small"
        aria-haspopup="menu"
        aria-controls="repertoire-add-to-setlist"
        :disabled="setlistsStore.setlists.length === 0 || isWorking"
        @click="addToMenu?.toggle($event)"
      />
      <Button
        label="Nouvelle setlist avec ces titres"
        size="small"
        severity="secondary"
        :disabled="isWorking"
        @click="newSetlistOpen = true"
      />
      <Button label="Archiver" size="small" severity="danger" outlined :disabled="isWorking" @click="confirmArchiveSelection" />
      <Button icon="pi pi-times" size="small" severity="secondary" text rounded aria-label="Tout désélectionner" @click="clearSelection" />
      <Menu id="repertoire-add-to-setlist" ref="addToMenu" :model="addToMenuItems" :popup="true" />
    </div>

    <Menu ref="actionsMenu" :model="menuItems" :popup="true" />

    <DurationPrompt ref="durationPrompt" :label="durationLabel" :saving="isSavingDuration" @submit="saveDuration" />

    <NewSetlistDialog v-model:visible="newSetlistOpen" :band-space-id="bandSpaceId" @created="fillNewSetlist" />

    <NewSongDialog v-model:visible="newSongOpen" :band-space-id="bandSpaceId" @created="openDrawer" />

    <SongDetailDrawer
      v-model:visible="drawerVisible"
      :band-space-id="bandSpaceId"
      :song="drawerSong"
      @archived="handleArchivedFromDrawer"
      @updated="drawerSong = $event"
    />
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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSongFileCounts } from '../../../composables/useSongFileCounts.js'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { filterCounts, filterSongs } from '../../../utils/repertoireFilters.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import DurationPrompt from './Editor/DurationPrompt.vue'
import NewSetlistDialog from './NewSetlistDialog.vue'
import NewSongDialog from './NewSongDialog.vue'
import SongDetailDrawer from './SongDetailDrawer.vue'

/**
 * The band's songs as a table (#1063): what each one is missing, which setlists play it, and a
 * selection to add several to a setlist, start one from them, or archive them.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** Song the command palette linked to, opened in the detail drawer once the repertoire is loaded. */
  focusSongId: { type: String, default: null }
})

const route = useRoute()
const router = useRouter()
const songsStore = useBandSongsStore()
const setlistsStore = useBandSetlistsStore()
const confirm = useConfirm()
const toast = useToast()

const query = ref('')
const withoutLyrics = ref(false)
const missingInfo = ref(false)
const newSongOpen = ref(false)
const drawerVisible = ref(false)
const drawerSong = ref(null)
const actionsMenu = ref(null)
const menuTargetSong = ref(null)

function showError(e) {
  toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
}

// Refreshed on mount and when the song drawer closes, where files are attached and detached.
const { counts: songFileCounts, load: loadSongsWithFiles } = useSongFileCounts(
  () => props.bandSpaceId
)

// Also re-read the songs: the setlists each one is in move whenever a setlist is edited, and the
// member usually comes back here from one.
onMounted(() => {
  loadSongsWithFiles()
  songsStore.fetchSongs(props.bandSpaceId)
})

watch(drawerVisible, (open, wasOpen) => {
  if (!wasOpen || open) return

  loadSongsWithFiles()

  // Dropping the parameter on close is what the finance and files modules already do, and it is
  // load bearing here rather than cosmetic: the deep link watcher below re-runs on every songs
  // mutation, so a lingering ?song= would swap the drawer back to the linked song after the member
  // had moved on to another one. It also stops a reload from reopening a drawer already dismissed.
  if (route.query.song) {
    router.replace({ query: { ...route.query, song: undefined } })
  }
})

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
      summary: `${ids.length} ${ids.length > 1 ? 'titres ajoutés' : 'titre ajouté'} à « ${setlist.name} »`,
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
    message: `Archiver ${ids.length} ${ids.length > 1 ? 'titres' : 'titre'} ? Ils seront retirés du répertoire actif et restent restaurables depuis la corbeille.`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      isWorking.value = true
      try {
        await songsStore.archiveSongs(props.bandSpaceId, ids)
        toast.add({
          severity: 'success',
          summary: ids.length > 1 ? 'Titres archivés' : 'Titre archivé',
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
const durationLabel = computed(() => `Durée de « ${durationSong.value?.title ?? ''} »`)

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

// --- One song ------------------------------------------------------------------------------------

const menuItems = computed(() => [
  {
    label: 'Modifier',
    icon: 'pi pi-pencil',
    command: () => openDrawer(menuTargetSong.value)
  },
  {
    label: 'Archiver',
    icon: 'pi pi-archive',
    command: () => confirmArchive(menuTargetSong.value)
  }
])

function openCreateDialog() {
  newSongOpen.value = true
}

function openDrawer(song) {
  drawerSong.value = song
  drawerVisible.value = true
}

/**
 * Watches the song list too, because the linked id usually arrives before the repertoire has
 * loaded. Re-opening is prevented by drawerSong keeping the id after a close, so dismissing the
 * drawer does not bounce it straight back.
 */
watch(
  [() => props.focusSongId, () => songsStore.songs],
  ([songId]) => {
    if (!songId || drawerSong.value?.id === songId) return

    const song = songsStore.songs.find((candidate) => candidate.id === songId)
    if (song) openDrawer(song)
  },
  { immediate: true }
)

function openMenu(event, song) {
  menuTargetSong.value = song
  actionsMenu.value?.toggle(event)
}

function handleArchivedFromDrawer() {
  drawerVisible.value = false
  drawerSong.value = null
}

function confirmArchive(song) {
  if (!song) return
  confirm.require({
    message: `Archiver le titre « ${song.title} » ? Il sera retiré du répertoire actif.`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await songsStore.deleteSong(props.bandSpaceId, song.id)
        toast.add({ severity: 'success', summary: 'Titre archivé', life: 3000 })
      } catch (e) {
        showError(e)
      }
    }
  })
}
</script>
