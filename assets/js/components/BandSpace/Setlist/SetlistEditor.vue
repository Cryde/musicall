<template>
  <div>
    <div v-if="setlistsStore.isLoadingActive && !setlist" class="flex flex-col gap-3">
      <Skeleton width="60%" height="2.5rem" />
      <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.75rem" />
    </div>

    <div
      v-else-if="!setlist"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 border border-surface-200 dark:border-surface-700 text-center text-surface-600 dark:text-surface-300"
    >
      Sélectionnez une setlist dans la barre latérale.
    </div>

    <div v-else class="flex gap-4 items-start">
      <section
        :aria-label="`Setlist ${setlist.name}`"
        class="flex-1 min-w-0 flex flex-col bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700"
      >
        <div class="flex flex-col gap-3 px-4 sm:px-6 pt-5 pb-4 border-b border-surface-200 dark:border-surface-700">
          <Message v-if="isArchived" severity="warn" :closable="false" icon="pi pi-archive">
            Cette setlist est archivée, elle est en lecture seule. Dupliquez-la pour repartir de son contenu.
          </Message>

          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 basis-full sm:basis-auto sm:flex-1 min-w-0">
              <InputText
                v-if="editingName"
                v-model="nameDraft"
                autofocus
                aria-label="Nom de la setlist"
                class="font-semibold text-xl w-full"
                @blur="commitRename"
                @keyup.enter="commitRename"
                @keyup.esc="cancelRename"
              />
              <template v-else>
                <h2 class="m-0 font-semibold text-2xl truncate">{{ setlist.name }}</h2>
                <Button
                  v-if="!isArchived"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  aria-label="Renommer la setlist"
                  @click="startRename"
                />
              </template>
            </div>
            <Button
              v-if="!panelInline || !panelOpen"
              label="Répertoire"
              icon="pi pi-book"
              severity="secondary"
              size="small"
              @click="openPanel"
            />
            <Button label="Exporter PDF" icon="pi pi-file-pdf" severity="secondary" size="small" @click="pdfPopover?.toggle($event)" />
            <Button
              label="Mode Live"
              icon="pi pi-play"
              size="small"
              :disabled="setlist.items.length === 0"
              v-tooltip.top="setlist.items.length === 0 ? 'Ajoutez au moins un titre' : null"
              @click="openLiveMode"
            />
            <Button
              icon="pi pi-ellipsis-h"
              severity="secondary"
              size="small"
              aria-label="Plus d’actions : fichiers, dupliquer, archiver"
              aria-haspopup="menu"
              @click="setlistMenu?.toggle($event)"
            />
          </div>

          <p v-if="rows.length === 0" class="m-0 text-sm text-surface-600 dark:text-surface-300">Aucun titre pour l’instant</p>
          <div v-else class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-surface-600 dark:text-surface-300">
            <span><strong class="text-surface-900 dark:text-surface-0 font-semibold">{{ summary.songs }}</strong> {{ summary.songs > 1 ? 'titres' : 'titre' }}</span>
            <span><strong class="text-surface-900 dark:text-surface-0 font-semibold">{{ summary.intermissions }}</strong> {{ summary.intermissions > 1 ? 'intermèdes' : 'intermède' }}</span>
            <span class="flex items-center gap-1.5">
              <i class="pi pi-clock text-xs" aria-hidden="true" />
              <strong class="text-surface-900 dark:text-surface-0 font-semibold tabular-nums">{{ formatDuration(summary.total) || '0:00' }}</strong>
            </span>
            <button
              v-if="summary.missingDurations > 0"
              type="button"
              class="flex items-center gap-1.5 h-7 px-3 rounded-full border border-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs disabled:cursor-default"
              :disabled="isArchived"
              @click="promptFirstMissingDuration"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true" />
              {{ summary.missingDurations }} {{ summary.missingDurations > 1 ? 'durées manquantes' : 'durée manquante' }}
            </button>
          </div>

          <div class="flex items-center gap-3 text-xs text-surface-600 dark:text-surface-300">
            <template v-if="progress">
              <span class="whitespace-nowrap">
                Objectif
                <button
                  type="button"
                  class="font-semibold text-surface-900 dark:text-surface-0 underline decoration-dotted underline-offset-2 disabled:no-underline"
                  :disabled="isArchived"
                  :aria-label="`Objectif de durée ${formatDuration(setlist.target_duration)}, modifier`"
                  @click="targetPrompt?.open($event, setlist.target_duration)"
                >{{ formatDuration(setlist.target_duration) }}</button>
              </span>
              <div
                class="flex-1 h-1.5 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden"
                role="progressbar"
                :aria-valuenow="Math.round(progress.ratio * 100)"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="Progression vers l’objectif de durée"
              >
                <div
                  class="h-full rounded-full"
                  :class="progress.isOver ? 'bg-red-500' : 'bg-primary'"
                  :style="{ width: `${progress.ratio * 100}%` }"
                />
              </div>
              <span class="whitespace-nowrap tabular-nums" :class="progress.isOver && 'text-red-700 dark:text-red-400 font-medium'">
                {{ progress.isOver ? `dépasse de ${formatDuration(-progress.remaining)}` : `reste ${formatDuration(progress.remaining) || '0:00'}` }}
              </span>
            </template>
            <Button
              v-else-if="!isArchived"
              label="Définir un objectif de durée"
              icon="pi pi-flag"
              severity="secondary"
              text
              size="small"
              class="!px-1"
              @click="targetPrompt?.open($event)"
            />
          </div>
        </div>

        <div
          v-if="rows.length > 0"
          class="flex items-center gap-2 sm:gap-3 pl-9 sm:pl-12 pr-2 sm:pr-4 pt-3 pb-1 text-[11px] font-semibold tracking-wide uppercase text-surface-600 dark:text-surface-300"
        >
          <span class="w-6">#</span>
          <span class="flex-1">Programme</span>
          <span v-if="columns.duration" class="w-16 text-right">Durée</span>
          <span v-if="columns.cumulative" class="hidden sm:inline w-16 text-right">Cumul</span>
          <Button
            icon="pi pi-sliders-h"
            severity="secondary"
            text
            rounded
            size="small"
            aria-label="Choisir les colonnes"
            aria-haspopup="menu"
            v-tooltip.top="'Colonnes'"
            @click="columnsPopover?.toggle($event)"
          />
        </div>

        <div class="relative px-2 sm:px-4 py-2">
          <!-- Over the list rather than in it: an empty list still has to be a place to drop a song. -->
          <SetlistEmptyState
            v-if="rows.length === 0"
            class="absolute inset-x-0 top-0 z-10"
            :sources="copySources"
            :song-count="songsStore.songs.length"
            :busy="emptyStateBusy"
            :readonly="isArchived"
            @copy-from="copyFrom"
            @add-all="addWholeRepertoire"
          />
          <SetlistInsertGap
            v-if="rows.length > 0 && !isArchived"
            :position="0"
            :active="insertPosition === 0"
            @insert-song="startInsertingAt"
            @insert-intermission="addIntermission"
            @cancel="stopInserting"
          />
          <VueDraggable
            v-model="localItems"
            :animation="200"
            :disabled="isArchived"
            :group="{ name: DRAG_GROUP, pull: false, put: true }"
            ghost-class="opacity-30"
            handle=".drag-handle"
            :class="['flex flex-col', rows.length === 0 && 'min-h-80']"
            @start="isDragging = true"
            @end="handleDragEnd"
            @add="handleDropFromRepertoire"
          >
            <div v-for="(row, index) in rows" :key="row.item.id">
              <SetlistProgrammeRow
                :row="row"
                :columns="columns"
                :file-count="row.item.song ? (songFileCounts.get(row.item.song.id) ?? 0) : 0"
                :readonly="isArchived"
                @edit="openItemEdit"
                @remove="removeItem"
                @menu="openItemMenu"
                @set-duration="promptSongDuration"
              />
              <SetlistInsertGap
                v-if="!isArchived"
                :position="index + 1"
                :active="insertPosition === index + 1"
                @insert-song="startInsertingAt"
                @insert-intermission="addIntermission"
                @cancel="stopInserting"
              />
            </div>
          </VueDraggable>
        </div>

        <div
          v-if="!isArchived"
          class="flex flex-wrap items-center gap-2 px-4 sm:px-6 py-3 border-t border-surface-200 dark:border-surface-700"
        >
          <Button
            v-for="kind in INTERMISSION_KINDS"
            :key="kind.type"
            :label="kind.label"
            :icon="kind.icon"
            severity="secondary"
            outlined
            size="small"
            class="!border-dashed"
            @click="addIntermission(kind.type, insertPosition)"
          />
          <span class="ml-auto text-xs text-surface-600 dark:text-surface-300 hidden md:inline">
            Glissez un titre du répertoire dans la liste, ou cliquez dessus
          </span>
        </div>
      </section>

      <aside
        v-if="panelInline && panelOpen"
        aria-label="Répertoire"
        class="w-80 shrink-0 sticky top-4 h-[calc(100vh-7rem)] bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden"
      >
        <SetlistRepertoirePanel
          :songs="songsStore.songs"
          :items="localItems"
          :insert-hint="insertHint"
          :readonly="isArchived"
          closable
          @add="addSong"
          @create-song="songDialogOpen = true"
          @close="panelOpen = false"
        />
      </aside>
    </div>

    <Drawer v-if="!panelInline" v-model:visible="panelOpen" position="right" :style="{ width: 'min(22rem, 100vw)' }" :show-close-icon="false">
      <SetlistRepertoirePanel
        :songs="songsStore.songs"
        :items="localItems"
        :insert-hint="insertHint"
        :readonly="isArchived"
        closable
        @add="addSong"
        @create-song="songDialogOpen = true"
        @close="panelOpen = false"
      />
    </Drawer>

    <Menu ref="itemMenu" :model="itemMenuModel" :popup="true" />
    <Menu ref="setlistMenu" :model="setlistMenuModel" :popup="true" />

    <Popover ref="columnsPopover">
      <fieldset class="flex flex-col gap-2 border-0 p-0 m-0">
        <legend class="text-sm font-semibold mb-1">Colonnes affichées</legend>
        <div v-for="column in COLUMNS" :key="column.key" class="flex items-center gap-2">
          <Checkbox v-model="columns[column.key]" :binary="true" :input-id="`setlist-column-${column.key}`" />
          <label :for="`setlist-column-${column.key}`" class="text-sm">{{ column.label }}</label>
        </div>
      </fieldset>
    </Popover>

    <DurationPrompt ref="durationPrompt" :label="durationPromptLabel" :saving="isSavingDuration" @submit="saveSongDuration" />
    <DurationPrompt ref="targetPrompt" label="Objectif de durée" :min-seconds="60" clearable :saving="isSavingTarget" @submit="saveTarget" />

    <SetlistItemEditDrawer
      v-model:visible="editDrawerOpen"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
      :item="editingItem"
    />

    <NewSongDialog v-model:visible="songDialogOpen" :band-space-id="bandSpaceId" @created="addNewSong" />

    <SongDetailDrawer
      v-model:visible="songDrawerOpen"
      :band-space-id="bandSpaceId"
      :song="drawerSong"
      @updated="handleSongUpdated"
      @archived="songDrawerOpen = false"
    />

    <PdfExportPopover
      ref="pdfPopover"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
      :item-count="setlist?.items?.length ?? 0"
    />

    <SetlistFileDrawer
      v-model:visible="filesDrawerOpen"
      :band-space-id="bandSpaceId"
      :setlist-id="setlistId"
      :readonly="isArchived"
    />
  </div>
</template>

<script setup>
import { useMediaQuery } from '@vueuse/core'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Popover from 'primevue/popover'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useRouter } from 'vue-router'
import { useSongFileCounts } from '../../../composables/useSongFileCounts.js'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import {
  COLUMNS,
  programmeRows,
  programmeSummary,
  readColumns,
  targetProgress,
  writeColumns
} from '../../../utils/setlistProgramme.js'
import DurationPrompt from './Editor/DurationPrompt.vue'
import { INTERMISSION_KINDS, intermissionKind } from './Editor/intermissionKinds.js'
import SetlistEmptyState from './Editor/SetlistEmptyState.vue'
import SetlistInsertGap from './Editor/SetlistInsertGap.vue'
import SetlistProgrammeRow from './Editor/SetlistProgrammeRow.vue'
import SetlistRepertoirePanel from './Editor/SetlistRepertoirePanel.vue'
import { DRAG_GROUP } from './Editor/setlistDrag.js'
import NewSongDialog from './NewSongDialog.vue'
import PdfExportPopover from './PdfExportPopover.vue'
import SetlistFileDrawer from './SetlistFileDrawer.vue'
import SetlistItemEditDrawer from './SetlistItemEditDrawer.vue'
import SongDetailDrawer from './SongDetailDrawer.vue'

/**
 * A setlist's running order beside the repertoire (#1061): songs and intermèdes with their running
 * total, the set's target duration, and the band's songs one click or one drag away.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const emit = defineEmits(['archived', 'duplicated'])

const setlistsStore = useBandSetlistsStore()
const songsStore = useBandSongsStore()
const confirm = useConfirm()
const toast = useToast()
const router = useRouter()

const setlist = computed(() => setlistsStore.activeSetlist)

// An archived setlist stays reachable through ?setlist=<id> and a tab left open, and the API refuses
// every write on it, so the editing affordances go too.
const isArchived = computed(() => Boolean(setlist.value?.archive_datetime))

const localItems = ref([])

/** Null is the end of the set; a number is the position the next added song takes. */
const insertPosition = ref(null)

// Not while a row is being dragged: replacing the list under SortableJS mid-gesture desyncs the DOM
// from the array. The latest items are taken as soon as the drag ends.
const isDragging = ref(false)

function syncLocalItems() {
  localItems.value = setlist.value?.items ? [...setlist.value.items] : []
}

watch(
  () => setlist.value?.items,
  () => {
    if (!isDragging.value) syncLocalItems()
  },
  { immediate: true }
)

watch(
  () => props.setlistId,
  (id) => {
    insertPosition.value = null
    if (id) {
      setlistsStore.fetchActive(props.bandSpaceId, id)
    }
  },
  { immediate: true }
)

const rows = computed(() => programmeRows(localItems.value))
const summary = computed(() => programmeSummary(localItems.value))
const progress = computed(() => targetProgress(summary.value.total, setlist.value?.target_duration))

const { counts: songFileCounts, load: loadSongFileCounts } = useSongFileCounts(
  () => props.bandSpaceId
)
onMounted(loadSongFileCounts)

function showError(e) {
  toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
}

// --- Columns, remembered per browser --------------------------------------------------------------

const columns = reactive(readColumns(globalThis.localStorage))
watch(columns, (value) => writeColumns(globalThis.localStorage, { ...value }))
const columnsPopover = ref(null)

// --- Repertoire panel: beside the set on a wide screen, a drawer below ---------------------------

const panelInline = useMediaQuery('(min-width: 1280px)')
const panelOpen = ref(panelInline.value)
watch(panelInline, (inline) => {
  panelOpen.value = inline
})

function openPanel() {
  panelOpen.value = true
}

// --- Inserting: where the next song goes -------------------------------------------------------

// Named after the row it follows rather than numbered: song numbers skip intermèdes, positions do not.
const insertHint = computed(() => {
  if (insertPosition.value === null) return null
  if (insertPosition.value === 0) return 'Les titres s’ajoutent en tête de la setlist.'
  const previous = localItems.value[insertPosition.value - 1]
  const name = previous?.song?.title ?? previous?.label ?? intermissionKind(previous?.type).label
  return `Les titres s’ajoutent après « ${name} ».`
})

function startInsertingAt(position) {
  insertPosition.value = position
  openPanel()
}

function stopInserting() {
  insertPosition.value = null
}

/** Answers whether the song made it into the set. */
async function addSong(song) {
  if (!song?.id || isArchived.value) return false
  const position = insertPosition.value
  try {
    await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
      type: 'song',
      song_id: song.id,
      ...(position === null ? {} : { position })
    })
    // The next one goes after it, so several clicks read in the order they were made.
    if (position !== null) insertPosition.value = position + 1
    return true
  } catch (e) {
    showError(e)
    return false
  }
}

/** Created with its kind as label, then opened, so naming it is the next step rather than a form first. */
async function addIntermission(type, position = null) {
  try {
    const created = await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
      type,
      label: intermissionKind(type).label,
      ...(position === null ? {} : { position })
    })
    if (position !== null && insertPosition.value !== null) insertPosition.value = position + 1
    openItemEdit(created)
  } catch (e) {
    showError(e)
  }
}

// A song dragged in from the repertoire lands in the list as a stand-in; the real item replaces it
// once the server has placed it at the same position.
async function handleDropFromRepertoire(event) {
  const pending = localItems.value[event.newIndex]
  if (!pending?.song) return
  try {
    await setlistsStore.addItem(props.bandSpaceId, props.setlistId, {
      type: 'song',
      song_id: pending.song.id,
      position: event.newIndex
    })
  } catch (e) {
    localItems.value = [...(setlist.value?.items ?? [])]
    showError(e)
  }
}

// --- Starting from nothing (#1062) --------------------------------------------------------------

const emptyStateBusy = ref(null)

// The trash too: an archived setlist is still a fine starting point, as it is for « Dupliquer ».
const copySources = computed(() =>
  [...setlistsStore.setlists, ...setlistsStore.archivedSetlists].filter(
    (candidate) => candidate.id !== props.setlistId && candidate.items?.length > 0
  )
)

async function copyFrom(source) {
  emptyStateBusy.value = 'copy'
  try {
    await setlistsStore.copyItemsFrom(props.bandSpaceId, props.setlistId, source.id)
    toast.add({ severity: 'success', summary: `Setlist « ${source.name} » reprise`, life: 3000 })
  } catch (e) {
    showError(e)
  } finally {
    emptyStateBusy.value = null
  }
}

/** In repertoire order, which is alphabetical: a starting point to cut down, not a running order. */
async function addWholeRepertoire() {
  emptyStateBusy.value = 'all'
  try {
    await setlistsStore.addSongs(
      props.bandSpaceId,
      props.setlistId,
      songsStore.songs.map((song) => song.id)
    )
  } catch (e) {
    showError(e)
  } finally {
    emptyStateBusy.value = null
  }
}

// --- Rename --------------------------------------------------------------------------------------

const editingName = ref(false)
const nameDraft = ref('')

function startRename() {
  if (isArchived.value) return
  nameDraft.value = setlist.value?.name ?? ''
  editingName.value = true
}

function cancelRename() {
  editingName.value = false
  nameDraft.value = ''
}

async function commitRename() {
  if (!editingName.value) return
  const trimmed = nameDraft.value.trim()
  editingName.value = false
  if (!trimmed || trimmed === setlist.value?.name) return
  try {
    await setlistsStore.renameSetlist(props.bandSpaceId, props.setlistId, trimmed)
    toast.add({ severity: 'success', summary: 'Setlist renommée', life: 3000 })
  } catch (e) {
    showError(e)
  }
}

// --- Durations -----------------------------------------------------------------------------------

const durationPrompt = ref(null)
const durationTarget = ref(null)
const isSavingDuration = ref(false)
const durationPromptLabel = computed(
  () => `Durée de « ${durationTarget.value?.song?.title ?? ''} »`
)

function promptSongDuration(event, item) {
  durationTarget.value = item
  durationPrompt.value?.open(event)
}

function promptFirstMissingDuration(event) {
  const first = rows.value.find((row) => row.missingDuration)
  if (first) promptSongDuration(event, first.item)
}

/**
 * Written on the song, not the item: a song's length is the same in every set, and filling it here
 * fills it everywhere the song is played.
 */
async function saveSongDuration(seconds) {
  const songId = durationTarget.value?.song?.id
  if (!songId || seconds === null) return
  isSavingDuration.value = true
  try {
    await songsStore.updateSong(props.bandSpaceId, songId, { reference_duration: seconds })
    setlistsStore.applySongChange(songId, { reference_duration: seconds })
    durationPrompt.value?.hide()
  } catch (e) {
    showError(e)
  } finally {
    isSavingDuration.value = false
  }
}

const targetPrompt = ref(null)
const isSavingTarget = ref(false)

async function saveTarget(seconds) {
  isSavingTarget.value = true
  try {
    await setlistsStore.setTargetDuration(props.bandSpaceId, props.setlistId, seconds)
    targetPrompt.value?.hide()
  } catch (e) {
    showError(e)
  } finally {
    isSavingTarget.value = false
  }
}

// --- Reorder, edit, remove ------------------------------------------------------------------------

async function handleDragEnd() {
  isDragging.value = false
  const orderedIds = localItems.value.filter((item) => !item.pending).map((item) => item.id)
  try {
    await setlistsStore.reorderItems(props.bandSpaceId, props.setlistId, orderedIds)
  } catch (e) {
    showError(e)
  }
}

const editDrawerOpen = ref(false)
const editingItem = ref(null)

function openItemEdit(item) {
  if (isArchived.value) return
  editingItem.value = item
  editDrawerOpen.value = true
}

/** Only asks when the row carries something the repertoire cannot give back: a note, a transition, its own duration. */
function removeItem(item) {
  const loses = item.note || item.transition || item.duration_override !== null
  if (!loses) {
    confirmedRemove(item)
    return
  }
  confirm.require({
    message:
      'Retirer cet élément de la setlist ? Sa note, sa transition et sa durée propre seront perdues.',
    header: 'Confirmer',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Retirer',
    rejectLabel: 'Annuler',
    accept: () => confirmedRemove(item)
  })
}

async function confirmedRemove(item) {
  try {
    await setlistsStore.removeItem(props.bandSpaceId, props.setlistId, item.id)
  } catch (e) {
    showError(e)
  }
}

const itemMenu = ref(null)
const menuTargetItem = ref(null)

function openItemMenu(event, item) {
  menuTargetItem.value = item
  itemMenu.value?.toggle(event)
}

const itemMenuModel = computed(() => {
  const item = menuTargetItem.value
  if (!item) return []
  const index = localItems.value.findIndex((candidate) => candidate.id === item.id)
  return [
    { label: 'Modifier', icon: 'pi pi-pencil', command: () => openItemEdit(item) },
    {
      label: 'Monter',
      icon: 'pi pi-arrow-up',
      disabled: index === 0,
      command: () => moveItem(item, -1)
    },
    {
      label: 'Descendre',
      icon: 'pi pi-arrow-down',
      disabled: index === localItems.value.length - 1,
      command: () => moveItem(item, +1)
    },
    { label: 'Insérer avant', icon: 'pi pi-plus', command: () => startInsertingAt(index) },
    { label: 'Insérer après', icon: 'pi pi-plus', command: () => startInsertingAt(index + 1) },
    { separator: true },
    { label: 'Retirer', icon: 'pi pi-trash', command: () => removeItem(item) }
  ]
})

async function moveItem(item, delta) {
  const index = localItems.value.findIndex((candidate) => candidate.id === item.id)
  const target = index + delta
  if (index < 0 || target < 0 || target >= localItems.value.length) return
  const next = [...localItems.value]
  const [moved] = next.splice(index, 1)
  next.splice(target, 0, moved)
  localItems.value = next
  await handleDragEnd()
}

// --- Setlist actions ------------------------------------------------------------------------------

const pdfPopover = ref(null)
const filesDrawerOpen = ref(false)
const songDialogOpen = ref(false)
const songDrawerOpen = ref(false)
const drawerSong = ref(null)

/** Added to the set where songs are going, then opened so its key, BPM and duration come next (#1067). */
async function addNewSong(song) {
  // A song the set refused stays in the repertoire, one click away once the error is dealt with.
  if (!(await addSong(song))) return
  drawerSong.value = song
  songDrawerOpen.value = true
}

function handleSongUpdated(song) {
  drawerSong.value = song
  setlistsStore.applySongChange(song.id, {
    title: song.title,
    tonality: song.tonality,
    tempo: song.tempo,
    reference_duration: song.reference_duration
  })
}
const setlistMenu = ref(null)

function openLiveMode() {
  router.push({
    name: 'app_band_setlist_live',
    params: { bandSpaceId: props.bandSpaceId, setlistId: props.setlistId }
  })
}

const setlistMenuModel = computed(() => [
  { label: 'Fichiers', icon: 'pi pi-folder', command: () => (filesDrawerOpen.value = true) },
  { label: 'Dupliquer', icon: 'pi pi-copy', command: handleDuplicate },
  ...(isArchived.value
    ? []
    : [
        { separator: true },
        { label: 'Archiver', icon: 'pi pi-archive', class: 'text-red-600', command: confirmArchive }
      ])
])

async function handleDuplicate() {
  try {
    const copy = await setlistsStore.duplicateSetlist(props.bandSpaceId, props.setlistId)
    toast.add({ severity: 'success', summary: 'Setlist dupliquée', life: 3000 })
    emit('duplicated', copy.id)
  } catch (e) {
    showError(e)
  }
}

function confirmArchive() {
  confirm.require({
    message: `Archiver la setlist «${setlist.value?.name}» ?`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await setlistsStore.archiveSetlist(props.bandSpaceId, props.setlistId)
        toast.add({ severity: 'success', summary: 'Setlist archivée', life: 3000 })
        emit('archived')
      } catch (e) {
        showError(e)
      }
    }
  })
}
</script>
