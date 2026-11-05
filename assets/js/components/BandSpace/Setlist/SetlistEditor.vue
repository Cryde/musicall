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
      {{ t('band_space_setlist.editor.no_selection') }}
    </div>

    <div v-else class="flex gap-4 items-start">
      <section
        :aria-label="t('band_space_setlist.editor.section_aria', { name: setlist.name })"
        class="flex-1 min-w-0 flex flex-col bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 max-md:-mx-6 max-md:-mt-8 max-md:min-h-[calc(100dvh-4rem)] max-md:rounded-none max-md:border-0 max-md:pb-24"
      >
        <!-- A phone shows the set as a screen of its own: back to the list, its name, and ⋮. -->
        <PhoneBackBar
          v-if="isBelowMd"
          variant="screen"
          sticky
          :back-label="t('band_space_setlist.phone.back_to_setlists')"
          :title="setlist.name"
          @back="emit('back')"
        >
          <template v-if="editingName" #title>
            <InputText
              v-model="nameDraft"
              autofocus
              :aria-label="t('band_space_setlist.editor.name_aria')"
              class="min-w-0 flex-1 font-semibold"
              @blur="commitRename"
              @keyup.enter="commitRename"
              @keyup.esc="cancelRename"
            />
          </template>
          <template #actions>
            <Button
              ref="phoneMoreButton"
              icon="pi pi-ellipsis-v"
              text
              rounded
              severity="secondary"
              :aria-label="t('band_space_setlist.phone.setlist_actions')"
              aria-haspopup="dialog"
              @click="isSetlistSheetOpen = true"
            />
          </template>
        </PhoneBackBar>

        <div class="flex flex-col gap-3 px-4 sm:px-6 pt-5 pb-4 border-b border-surface-200 dark:border-surface-700 max-md:gap-2 max-md:border-b-0 max-md:pt-3 max-md:pb-0">
          <Message v-if="isArchived" severity="warn" :closable="false" icon="pi pi-archive">
            {{ t('band_space_setlist.editor.archived_notice') }}
          </Message>

          <div v-if="!isBelowMd" class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 basis-full sm:basis-auto sm:flex-1 min-w-0">
              <InputText
                v-if="editingName"
                v-model="nameDraft"
                autofocus
                :aria-label="t('band_space_setlist.editor.name_aria')"
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
                  :aria-label="t('band_space_setlist.editor.rename_aria')"
                  @click="startRename"
                />
              </template>
            </div>
            <Button
              v-if="!panelInline || !panelOpen"
              :label="t('band_space_setlist.common.repertoire')"
              icon="pi pi-book"
              severity="secondary"
              size="small"
              @click="openPanel"
            />
            <Button :label="t('band_space_setlist.editor.export_pdf')" icon="pi pi-file-pdf" severity="secondary" size="small" @click="pdfPopover?.toggle($event)" />
            <Button
              :label="t('band_space_setlist.editor.live_mode')"
              icon="pi pi-play"
              size="small"
              :disabled="setlist.items.length === 0"
              v-tooltip.top="setlist.items.length === 0 ? t('band_space_setlist.editor.live_mode_needs_song') : null"
              @click="openLiveMode"
            />
            <Button
              icon="pi pi-ellipsis-h"
              severity="secondary"
              size="small"
              :aria-label="t('band_space_setlist.editor.more_actions_aria')"
              aria-haspopup="menu"
              @click="setlistMenu?.toggle($event)"
            />
          </div>

          <p v-if="rows.length === 0" class="m-0 text-sm text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.editor.empty') }}</p>
          <div v-else class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-surface-600 dark:text-surface-300">
            <i18n-t keypath="band_space_setlist.editor.song_count" tag="span" :plural="summary.songs">
              <template #count><strong class="text-surface-900 dark:text-surface-0 font-semibold">{{ summary.songs }}</strong></template>
            </i18n-t>
            <i18n-t keypath="band_space_setlist.editor.intermission_count" tag="span" :plural="summary.intermissions">
              <template #count><strong class="text-surface-900 dark:text-surface-0 font-semibold">{{ summary.intermissions }}</strong></template>
            </i18n-t>
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
              {{ t('band_space_setlist.editor.missing_durations', summary.missingDurations) }}
            </button>
          </div>

          <div class="flex items-center gap-3 text-xs text-surface-600 dark:text-surface-300">
            <template v-if="progress">
              <i18n-t keypath="band_space_setlist.editor.target" tag="span" class="whitespace-nowrap">
                <template #duration>
                  <button
                    type="button"
                    class="font-semibold text-surface-900 dark:text-surface-0 underline decoration-dotted underline-offset-2 disabled:no-underline"
                    :disabled="isArchived"
                    :aria-label="t('band_space_setlist.editor.target_edit_aria', { duration: formatDuration(setlist.target_duration) })"
                    @click="targetPrompt?.open($event, setlist.target_duration)"
                  >{{ formatDuration(setlist.target_duration) }}</button>
                </template>
              </i18n-t>
              <div
                class="flex-1 h-1.5 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden"
                role="progressbar"
                :aria-valuenow="Math.round(progress.ratio * 100)"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('band_space_setlist.editor.target_progress_aria')"
              >
                <div
                  class="h-full rounded-full"
                  :class="progress.isOver ? 'bg-red-500' : 'bg-primary'"
                  :style="{ width: `${progress.ratio * 100}%` }"
                />
              </div>
              <span class="whitespace-nowrap tabular-nums" :class="progress.isOver && 'text-red-700 dark:text-red-400 font-medium'">
                {{
                  progress.isOver
                    ? t('band_space_setlist.editor.target_over', { duration: formatDuration(-progress.remaining) })
                    : t('band_space_setlist.editor.target_remaining', { duration: formatDuration(progress.remaining) || '0:00' })
                }}
              </span>
            </template>
            <Button
              v-else-if="!isArchived"
              :label="t('band_space_setlist.editor.set_target')"
              icon="pi pi-flag"
              severity="secondary"
              text
              size="small"
              class="!px-1"
              @click="targetPrompt?.open($event)"
            />
          </div>

          <template v-if="isBelowMd">
            <Button
              :label="t('band_space_setlist.editor.live_mode')"
              icon="pi pi-play"
              class="mt-1 h-11.5! w-full"
              :disabled="setlist.items.length === 0"
              @click="openLiveMode"
            />
            <p class="m-0 pt-2 text-[0.8125rem] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
              {{ t('band_space_setlist.phone.programme') }}
            </p>
          </template>
        </div>

        <div
          v-if="rows.length > 0 && !isBelowMd"
          class="flex items-center gap-2 sm:gap-3 pl-9 sm:pl-12 pr-2 sm:pr-4 pt-3 pb-1 text-[11px] font-semibold tracking-wide uppercase text-surface-600 dark:text-surface-300"
        >
          <span class="w-6">#</span>
          <span class="flex-1">{{ t('band_space_setlist.editor.column_programme') }}</span>
          <span v-if="columns.duration" class="w-16 text-right">{{ t('band_space_setlist.column.duration') }}</span>
          <span v-if="columns.cumulative" class="hidden sm:inline w-16 text-right">{{ t('band_space_setlist.column.cumulative') }}</span>
          <Button
            icon="pi pi-sliders-h"
            severity="secondary"
            text
            rounded
            size="small"
            :aria-label="t('band_space_setlist.editor.columns_aria')"
            aria-haspopup="menu"
            v-tooltip.top="t('band_space_setlist.editor.columns_tooltip')"
            @click="columnsPopover?.toggle($event)"
          />
        </div>

        <div class="relative px-2 sm:px-4 py-2 max-md:px-2.5 max-md:pt-1 max-md:pb-3">
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
            v-if="rows.length > 0 && !isArchived && !isBelowMd"
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
            <div v-for="(row, index) in rows" :key="row.item.id" class="max-md:mb-1.5">
              <SetlistPhoneRow
                v-if="isBelowMd"
                :row="row"
                :readonly="isArchived"
                @open-song="openSongScreen"
                @read-lyrics="readLyricsFrom"
                @edit="openItemEdit"
                @menu="openItemSheet"
                @set-duration="promptSongDuration"
              />
              <SetlistProgrammeRow
                v-else
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
                v-if="!isArchived && !isBelowMd"
                :position="index + 1"
                :active="insertPosition === index + 1"
                @insert-song="startInsertingAt"
                @insert-intermission="addIntermission"
                @cancel="stopInserting"
              />
            </div>
          </VueDraggable>
        </div>

        <!-- A phone keeps the adds at the bottom of the screen, where the thumb is. -->
        <div
          v-if="!isArchived && isBelowMd"
          class="fixed inset-x-0 bottom-0 z-20 flex gap-1.5 border-t border-surface-200 bg-surface-0 px-3 pt-2.5 pb-[calc(1.125rem+env(safe-area-inset-bottom))] dark:border-surface-700 dark:bg-surface-900"
        >
          <Button :label="t('band_space_setlist.phone.add_songs_short')" icon="pi pi-plus" class="h-10! flex-1 !px-1 !text-sm" @click="openPanel" />
          <Button
            v-for="kind in INTERMISSION_KINDS"
            :key="kind.type"
            :label="kind.label"
            severity="secondary"
            outlined
            class="h-10! flex-1 !px-1 !text-sm"
            @click="addIntermission(kind.type, insertPosition)"
          />
        </div>
        <div
          v-else-if="!isArchived"
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
            {{ t('band_space_setlist.editor.drag_hint') }}
          </span>
        </div>
      </section>

      <aside
        v-if="panelInline && panelOpen"
        :aria-label="t('band_space_setlist.common.repertoire')"
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

    <Drawer v-if="!panelInline && !isBelowMd" v-model:visible="panelOpen" position="right" :style="{ width: 'min(22rem, 100vw)' }" :show-close-icon="false">
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
        <legend class="text-sm font-semibold mb-1">{{ t('band_space_setlist.editor.columns_legend') }}</legend>
        <div v-for="column in COLUMNS" :key="column.key" class="flex items-center gap-2">
          <Checkbox v-model="columns[column.key]" :binary="true" :input-id="`setlist-column-${column.key}`" />
          <label :for="`setlist-column-${column.key}`" class="text-sm">{{ column.label }}</label>
        </div>
      </fieldset>
    </Popover>

    <DurationPrompt ref="durationPrompt" :label="durationPromptLabel" :saving="isSavingDuration" @submit="saveSongDuration" />
    <DurationPrompt ref="targetPrompt" :label="t('band_space_setlist.editor.target_prompt_label')" :min-seconds="60" clearable :saving="isSavingTarget" @submit="saveTarget" />

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
      @read-lyrics="readLyricsOfSong"
    />

    <template v-if="isBelowMd">
      <SetlistAddSongsSheet
        v-model:visible="isAddSheetOpen"
        :songs="songsStore.songs"
        :items="localItems"
        :hint="insertHint"
        :add="addSongsAtInsertPoint"
        @after-hide="stopInserting"
      />

      <BottomSheet v-model:visible="isSetlistSheetOpen" :title="setlist?.name ?? ''" @after-hide="setlistSheet.onAfterHide">
        <SheetAction v-if="!isArchived" icon="pi-pencil" @click="setlistSheet.run(startRename)">
          {{ t('band_space_setlist.phone.rename') }}
        </SheetAction>
        <SheetAction icon="pi-copy" @click="setlistSheet.run(handleDuplicate)">
          {{ t('band_space_setlist.editor.duplicate') }}
        </SheetAction>
        <SheetAction icon="pi-file-pdf" @click="setlistSheet.run(openPdfFromBar)">
          {{ t('band_space_setlist.phone.export_pdf') }}
        </SheetAction>
        <SheetAction icon="pi-folder" @click="setlistSheet.run(() => (filesDrawerOpen = true))">
          {{ t('band_space_setlist.editor.files') }}
        </SheetAction>
        <SheetAction v-if="!isArchived" icon="pi-archive" danger @click="setlistSheet.run(confirmArchive)">
          {{ t('band_space_setlist.common.archive') }}
        </SheetAction>
      </BottomSheet>

      <BottomSheet v-model:visible="isItemSheetOpen" :title="itemSheetTitle" @after-hide="itemSheet.onAfterHide">
        <template #header>
          <span class="flex min-w-0 flex-col">
            <span class="truncate text-lg font-semibold text-surface-900 dark:text-surface-0">{{ itemSheetTitle }}</span>
            <span v-if="itemSheetFacts" class="text-[0.8125rem] text-surface-600 tabular-nums dark:text-surface-300">{{ itemSheetFacts }}</span>
          </span>
        </template>
        <SheetAction icon="pi-pencil" @click="itemSheet.run(() => openItemEdit(menuTargetItem))">
          {{ t('band_space_setlist.phone.edit_item') }}
        </SheetAction>
        <SheetAction icon="pi-arrow-up" :disabled="itemSheetIndex === 0" @click="itemSheet.run(() => moveItem(menuTargetItem, -1))">
          {{ t('band_space_setlist.editor.move_up') }}
        </SheetAction>
        <SheetAction
          icon="pi-arrow-down"
          :disabled="itemSheetIndex === localItems.length - 1"
          @click="itemSheet.run(() => moveItem(menuTargetItem, +1))"
        >
          {{ t('band_space_setlist.editor.move_down') }}
        </SheetAction>
        <SheetAction icon="pi-plus" @click="itemSheet.run(() => startInsertingAt(itemSheetIndex + 1))">
          {{ t('band_space_setlist.editor.insert_after') }}
        </SheetAction>
        <SheetAction icon="pi-trash" danger @click="itemSheet.run(() => removeItem(menuTargetItem))">
          {{ t('band_space_setlist.common.remove') }}
        </SheetAction>
      </BottomSheet>

      <LyricsReader v-model:visible="isReaderOpen" :band-space-id="bandSpaceId" :songs="readerSongs" :start-index="readerStart" />
    </template>

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
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useSongFileCounts } from '../../../composables/useSongFileCounts.js'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { recordOpenedItem } from '../../../utils/openedItemRecorder.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import {
  COLUMNS,
  programmeRows,
  programmeSummary,
  readColumns,
  targetProgress,
  writeColumns
} from '../../../utils/setlistProgramme.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import PhoneBackBar from '../../Global/PhoneBackBar.vue'
import SheetAction from '../../Global/SheetAction.vue'
import DurationPrompt from './Editor/DurationPrompt.vue'
import { INTERMISSION_KINDS, intermissionKind } from './Editor/intermissionKinds.js'
import SetlistAddSongsSheet from './Editor/SetlistAddSongsSheet.vue'
import SetlistEmptyState from './Editor/SetlistEmptyState.vue'
import SetlistInsertGap from './Editor/SetlistInsertGap.vue'
import SetlistPhoneRow from './Editor/SetlistPhoneRow.vue'
import SetlistProgrammeRow from './Editor/SetlistProgrammeRow.vue'
import SetlistRepertoirePanel from './Editor/SetlistRepertoirePanel.vue'
import { DRAG_GROUP } from './Editor/setlistDrag.js'
import LyricsReader from './Lyrics/LyricsReader.vue'
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

const emit = defineEmits(['archived', 'duplicated', 'back'])
const isBelowMd = useIsBelowMd()

const { t } = useI18n()
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
      setlistsStore.fetchActive(props.bandSpaceId, id).then(() => {
        // Once it has loaded, and only if it is still the one shown (#1047).
        if (props.setlistId === id && setlistsStore.activeSetlist?.id === id) {
          recordOpenedItem(props.bandSpaceId, 'setlist', id)
        }
      })
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
  toast.add({
    severity: 'error',
    summary: t('band_space_setlist.common.error'),
    detail: e.message,
    life: 5000
  })
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

// A phone adds through « Ajouter des titres », several songs at once, where the desktop opens the
// repertoire beside the set.
function openPanel() {
  if (isBelowMd.value) isAddSheetOpen.value = true
  else panelOpen.value = true
}

// --- Inserting: where the next song goes -------------------------------------------------------

// Named after the row it follows rather than numbered: song numbers skip intermèdes, positions do not.
const insertHint = computed(() => {
  if (insertPosition.value === null) return null
  if (insertPosition.value === 0) return t('band_space_setlist.editor.insert_hint_first')
  const previous = localItems.value[insertPosition.value - 1]
  const name = previous?.song?.title ?? previous?.label ?? intermissionKind(previous?.type).label
  return t('band_space_setlist.editor.insert_hint_after', { name })
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
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.editor.copied_from', { name: source.name }),
      life: 3000
    })
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
    toast.add({ severity: 'success', summary: t('band_space_setlist.editor.renamed'), life: 3000 })
  } catch (e) {
    showError(e)
  }
}

// --- Durations -----------------------------------------------------------------------------------

const durationPrompt = ref(null)
const durationTarget = ref(null)
const isSavingDuration = ref(false)
const durationPromptLabel = computed(() =>
  t('band_space_setlist.common.duration_of_song', {
    title: durationTarget.value?.song?.title ?? ''
  })
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
    message: t('band_space_setlist.editor.remove_confirm_message'),
    header: t('band_space_setlist.common.confirm'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.remove'),
    rejectLabel: t('band_space_setlist.common.cancel'),
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
    {
      label: t('band_space_setlist.common.edit'),
      icon: 'pi pi-pencil',
      command: () => openItemEdit(item)
    },
    {
      label: t('band_space_setlist.editor.move_up'),
      icon: 'pi pi-arrow-up',
      disabled: index === 0,
      command: () => moveItem(item, -1)
    },
    {
      label: t('band_space_setlist.editor.move_down'),
      icon: 'pi pi-arrow-down',
      disabled: index === localItems.value.length - 1,
      command: () => moveItem(item, +1)
    },
    {
      label: t('band_space_setlist.editor.insert_before'),
      icon: 'pi pi-plus',
      command: () => startInsertingAt(index)
    },
    {
      label: t('band_space_setlist.editor.insert_after'),
      icon: 'pi pi-plus',
      command: () => startInsertingAt(index + 1)
    },
    { separator: true },
    {
      label: t('band_space_setlist.common.remove'),
      icon: 'pi pi-trash',
      command: () => removeItem(item)
    }
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
  {
    label: t('band_space_setlist.editor.files'),
    icon: 'pi pi-folder',
    command: () => (filesDrawerOpen.value = true)
  },
  { label: t('band_space_setlist.editor.duplicate'), icon: 'pi pi-copy', command: handleDuplicate },
  ...(isArchived.value
    ? []
    : [
        { separator: true },
        {
          label: t('band_space_setlist.common.archive'),
          icon: 'pi pi-archive',
          class: 'text-red-600',
          command: confirmArchive
        }
      ])
])

async function handleDuplicate() {
  try {
    const copy = await setlistsStore.duplicateSetlist(props.bandSpaceId, props.setlistId)
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.editor.duplicated'),
      life: 3000
    })
    emit('duplicated', copy.id)
  } catch (e) {
    showError(e)
  }
}

function confirmArchive() {
  confirm.require({
    message: t('band_space_setlist.editor.archive_confirm_message', { name: setlist.value?.name }),
    header: t('band_space_setlist.common.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await setlistsStore.archiveSetlist(props.bandSpaceId, props.setlistId)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.editor.archived'),
          life: 3000
        })
        emit('archived')
      } catch (e) {
        showError(e)
      }
    }
  })
}

// --- Phone ------------------------------------------------------------------------------------------

const isAddSheetOpen = ref(false)
const isSetlistSheetOpen = ref(false)
const isItemSheetOpen = ref(false)
// Each runs its action once its sheet has gone: a confirm, a drawer or the next sheet opened while it
// leaves would lose the page's scroll lock.
const setlistSheet = useActionAfterSheetHide(isSetlistSheetOpen)
const itemSheet = useActionAfterSheetHide(isItemSheetOpen)
const phoneMoreButton = ref(null)

/** The ticked songs, in one request, where the set is being filled; the end of it otherwise. */
async function addSongsAtInsertPoint(songIds) {
  try {
    await setlistsStore.addSongs(props.bandSpaceId, props.setlistId, songIds, insertPosition.value)
    return true
  } catch (e) {
    showError(e)
    return false
  }
}

// The PDF options pop up from the bar's ⋮, the sheet that offered them being gone.
function openPdfFromBar() {
  const anchor = phoneMoreButton.value?.$el
  if (anchor) pdfPopover.value?.toggle({ currentTarget: anchor, target: anchor })
}

function openItemSheet(item) {
  menuTargetItem.value = item
  isItemSheetOpen.value = true
}

const itemSheetIndex = computed(() =>
  localItems.value.findIndex((candidate) => candidate.id === menuTargetItem.value?.id)
)
const itemSheetTitle = computed(() => {
  const item = menuTargetItem.value
  return item?.song?.title ?? item?.label ?? intermissionKind(item?.type).label ?? ''
})
// « 3 · E · 3:21 »: the song's number, key and duration, as the row says them.
const itemSheetFacts = computed(() => {
  const row = rows.value.find((candidate) => candidate.item.id === menuTargetItem.value?.id)
  if (!row?.isSong) return null
  return [row.number, row.item.song?.tonality, formatDuration(row.duration)]
    .filter(Boolean)
    .join(' · ')
})

/** A row's song, as the repertoire knows it: its drawer edits the song itself. */
function openSongScreen(item) {
  const song = songsStore.songs.find((candidate) => candidate.id === item.song?.id)
  if (!song) return
  drawerSong.value = song
  songDrawerOpen.value = true
}

// The reader turns through the set's songs in their running order.
const isReaderOpen = ref(false)
const readerStart = ref(0)
const readerSongs = computed(() =>
  rows.value.filter((row) => row.isSong && row.item.song).map((row) => row.item.song)
)

function readLyricsOfSong(song) {
  const at = readerSongs.value.findIndex((candidate) => candidate.id === song.id)
  readerStart.value = Math.max(at, 0)
  isReaderOpen.value = true
}

function readLyricsFrom(item) {
  readLyricsOfSong(item.song)
}

watch(isBelowMd, () => {
  isAddSheetOpen.value = false
  isSetlistSheetOpen.value = false
  isItemSheetOpen.value = false
})
</script>
