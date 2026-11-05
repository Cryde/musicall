<template>
  <div :class="{ 'max-md:pb-24': mobileView === 'tree' }">
    <h1 v-if="isBelowMd && mobileView === 'tree'" class="mb-3 text-xl font-bold">{{ t('band_space_notes.page.heading') }}</h1>

    <!-- On md and up the card clips its corners. Not on a phone, where a clipping ancestor would stop
         the editor's toolbar from sticking while the note is typed in. -->
    <div
      class="flex bg-surface-0 dark:bg-surface-900 rounded-2xl md:overflow-hidden md:min-h-[600px]"
    >
      <!-- Sidebar: always visible on md+, toggle on mobile -->
      <div
        class="md:border-r border-surface-200 dark:border-surface-700 flex-shrink-0 overflow-hidden"
        :class="mobileView === 'editor' ? 'hidden md:block md:w-56 lg:w-72' : 'w-full md:w-56 lg:w-72'"
      >
        <NoteTree
          ref="noteTreeRef"
          :nodes="notesStore.tree"
          :selectedKey="notesStore.selectedNoteId"
          @select="handleSelect"
          @create-root="openCreateDialog(null)"
          @create-child="openCreateDialog"
          @delete="handleDelete"
        />
      </div>

      <!-- Main content: always visible on md+, toggle on mobile -->
      <div
        class="flex-1 flex flex-col min-w-0"
        :class="mobileView === 'tree' ? 'hidden md:flex' : ''"
      >
        <!-- A phone's bar over the note: back to the list, where the save stands, and the note's ⋯.
             Stuck under the site header; the editor's toolbar sticks under it. -->
        <div
          v-if="mobileView === 'editor'"
          class="md:hidden sticky top-16 z-30 flex h-13 items-center gap-1 rounded-t-2xl border-b border-surface-200 bg-surface-0 px-1 dark:border-surface-700 dark:bg-surface-900"
        >
          <Button
            icon="pi pi-arrow-left"
            :aria-label="t('band_space_notes.page.back')"
            text
            rounded
            severity="secondary"
            @click="handleBack"
          />
          <span class="min-w-0 flex-1 truncate text-sm text-surface-600 dark:text-surface-300">{{ t('band_space_notes.page.heading') }}</span>
          <NoteSaveStatus :status="notesStore.saveStatus" />
          <Button
            v-if="notesStore.selectedNoteId && noteTreeRef?.hasActions(notesStore.selectedNoteId)"
            icon="pi pi-ellipsis-h"
            text
            rounded
            severity="secondary"
            :aria-label="t('band_space_notes.tree.note_actions')"
            aria-haspopup="dialog"
            @click="noteTreeRef.openActions(notesStore.selectedNoteId, $event)"
          />
        </div>

        <div v-if="notesStore.isLoading" class="flex items-center justify-center flex-1">
          <ProgressSpinner />
        </div>

        <div v-else-if="notesStore.loadError" class="flex items-center justify-center flex-1 p-8">
          <Message severity="error" :closable="false">{{ notesStore.loadError }}</Message>
        </div>

        <div v-else-if="notesStore.isLoadingNote" class="flex items-center justify-center flex-1">
          <ProgressSpinner />
        </div>

        <!-- The reload count is in the key so an explicit reload rebuilds the editor around the note
             that came back. Selecting or saving never changes it, so the caret survives both. -->
        <NoteEditor
          v-else-if="notesStore.selectedNote"
          :key="`${notesStore.selectedNoteId}-${notesStore.selectedNoteReloadCount}`"
          :note="notesStore.selectedNote"
          :band-space-id="bandSpaceId"
          :saveStatus="notesStore.saveStatus"
          :isReloading="notesStore.isReloadingNote"
          @update-title="handleUpdateTitle"
          @update-emoji="handleUpdateEmoji"
          @reload="handleReloadNote"
          @pending-edits-change="editorHasPendingEdits = $event"
        />

        <div v-else class="hidden md:flex flex-col items-center justify-center flex-1 text-center p-8">
          <i class="pi pi-file-edit text-5xl text-surface-300 dark:text-surface-600 mb-4"></i>
          <p class="text-lg text-surface-500 dark:text-surface-400">
            {{ t('band_space_notes.page.select_prompt') }}
          </p>
        </div>
      </div>

      <!-- On a phone « Nouvelle note » floats where the thumb is, as on the other modules. -->
      <Button
        v-if="isBelowMd && mobileView === 'tree'"
        :label="t('band_space_notes.tree.new_note')"
        icon="pi pi-plus"
        rounded
        class="fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
        @click="openCreateDialog(null)"
      />

      <!-- Create Note Dialog -->
      <CreateNoteDialog
        v-model:visible="showCreateDialog"
        :parentId="createParentId"
        @created="handleCreateNote"
      />

    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import CreateNoteDialog from '../../components/BandSpace/Notes/CreateNoteDialog.vue'
import NoteEditor from '../../components/BandSpace/Notes/NoteEditor.vue'
import NoteSaveStatus from '../../components/BandSpace/Notes/NoteSaveStatus.vue'
import NoteTree from '../../components/BandSpace/Notes/NoteTree.vue'
import { useBandSpaceLiveRefresh } from '../../composables/useBandSpaceLiveRefresh.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { useBandSpaceNotesStore } from '../../store/bandSpace/bandSpaceNotes.js'
import { recordOpenedItem } from '../../utils/openedItemRecorder.js'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
const notesStore = useBandSpaceNotesStore()
// Wipe any previous space's notes synchronously before first render to avoid
// flashing A's tree when switching to B (the :key on <router-view> remounts
// this view but the Pinia store keeps A's state until cleared).
notesStore.clear()

const showCreateDialog = ref(false)
const createParentId = ref(null)
const mobileView = ref('tree') // 'tree' | 'editor'
const isBelowMd = useIsBelowMd()
const noteTreeRef = ref(null)
// Only one editor is ever mounted, the open note's, so one flag is the whole answer.
const editorHasPendingEdits = ref(false)

const bandSpaceId = route.params.id

onMounted(() => {
  window.addEventListener('beforeunload', warnOnUnload)
  notesStore.loadNotes(bandSpaceId)
})

useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['notes'],
  refresh: () => notesStore.refreshFromLiveSignal(bandSpaceId, () => editorHasPendingEdits.value)
})

onBeforeUnmount(() => window.removeEventListener('beforeunload', warnOnUnload))

onUnmounted(() => {
  notesStore.clear()
})

/**
 * Covers closing the tab and reloading, which the router never sees and which the flush on unmount
 * never runs for. Up to two seconds of typing lives on the debounce, and a save the server refused
 * lives nowhere else at all.
 *
 * The browser shows its own wording here; returnValue is what makes it show anything at all.
 */
function warnOnUnload(event) {
  if (!editorHasPendingEdits.value && !notesStore.hasUnsavedContent) return
  event.preventDefault()
  event.returnValue = ''
}

onBeforeRouteLeave(() => notesStore.confirmDiscardingEdits())

// Switching band space changes only the id param, which keeps this route matched and so never
// fires onBeforeRouteLeave. The keyed router-view then rebuilds this view around the new space and
// the notes of the old one are gone, so the question has to be asked here, before the param change
// is committed.
onBeforeRouteUpdate(() => notesStore.confirmDiscardingEdits())

/**
 * Guarded like the route guards above, and for the same reason: opening another note unmounts the
 * editor holding this one, so text a save was refused for goes with it. This is the exit path the
 * conflict banner tells the member to copy their text before taking, so it is the one that most
 * has to enforce what the banner says.
 *
 * The route leave predicate is the right one here rather than the tab close one: the unmount
 * flushes whatever is still on the debounce, so pending keystrokes are on their way to the server
 * and only a save already refused is actually lost.
 *
 * @returns {boolean} false when the member chose to keep what is open
 */
function openNote(noteId) {
  // Re-opening the note already on screen unmounts nothing, so there is nothing to ask about.
  if (noteId === notesStore.selectedNoteId) return true

  // Kept rather than switched: a phone shows the note the member chose to keep.
  if (!notesStore.confirmDiscardingEdits()) {
    if (notesStore.selectedNoteId) mobileView.value = 'editor'
    return false
  }

  // However it was asked for (the tree, a link, « Créer »), a phone shows the note it opens.
  mobileView.value = 'editor'
  notesStore.selectNote(bandSpaceId, noteId).then(() => {
    // Once it has loaded, so a note deleted since is not recorded, and not if another took its place (#1047).
    if (notesStore.selectedNoteId === noteId && notesStore.selectedNote) {
      recordOpenedItem(bandSpaceId, 'note', noteId)
    }
  })

  return true
}

function handleSelect(noteId) {
  // Opening the note already open still has to bring a phone back to it.
  if (openNote(noteId)) mobileView.value = 'editor'
}

function handleBack() {
  mobileView.value = 'tree'
}

/**
 * The open note is URL state, so the command palette and the activity log can both link straight to
 * one. selectNote() fetches the note by id on its own, so this does not have to wait for the tree.
 *
 * Deliberately routed through openNote(): arriving from a link unmounts the editor exactly like
 * clicking another note in the tree does, so it has to ask the same question about unsaved text.
 */
watch(
  () => route.query.note,
  (noteId) => {
    if (typeof noteId === 'string' && noteId && noteId !== notesStore.selectedNoteId) {
      openNote(noteId)
    }
  },
  { immediate: true }
)

watch(
  () => notesStore.selectedNoteId,
  (noteId) => {
    // The open note was deleted: a phone goes back to the list rather than an empty screen. Not when
    // it failed to load, whose error is shown where the note would have been.
    if (!noteId && !notesStore.loadError) mobileView.value = 'tree'
    if (noteId && noteId !== route.query.note) {
      router.replace({ query: { ...route.query, note: noteId } })
    }
  }
)

function openCreateDialog(parentId = null) {
  createParentId.value = parentId
  showCreateDialog.value = true
}

async function handleCreateNote({ title, parentId }) {
  try {
    const newNote = await notesStore.createNote(bandSpaceId, title, parentId)
    if (parentId) {
      noteTreeRef.value?.expandNode(parentId)
    }
    toast.add({
      severity: 'success',
      summary: t('band_space_notes.page.created'),
      life: 3000
    })
    // Created either way, opened only if the note being left has nothing to lose. Same unmount,
    // same loss, so the same question as clicking another note in the tree.
    openNote(newNote.id)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.page.error_summary'),
      detail: error.message || t('band_space_notes.page.create_error'),
      life: 5000
    })
  }
}

async function handleUpdateTitle(title) {
  if (!notesStore.selectedNoteId) return

  const { status } = await notesStore.updateNoteTitle(bandSpaceId, notesStore.selectedNoteId, title)
  if (status === 'error') {
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.page.error_summary'),
      detail: t('band_space_notes.page.title_update_error'),
      life: 5000
    })
  }
}

async function handleUpdateEmoji(emoji) {
  if (!notesStore.selectedNoteId) return

  const { status } = await notesStore.updateNoteEmoji(bandSpaceId, notesStore.selectedNoteId, emoji)
  if (status === 'error') {
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.page.error_summary'),
      detail: t('band_space_notes.page.emoji_update_error'),
      life: 5000
    })
  }
}

async function handleReloadNote() {
  try {
    await notesStore.reloadSelectedNote(bandSpaceId)
  } catch (error) {
    // The editor is untouched on failure, so the member still has their text and can retry.
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.page.error_summary'),
      detail: error.message || t('band_space_notes.page.reload_error'),
      life: 5000
    })
  }
}

function handleDelete(noteId) {
  confirm.require({
    message: t('band_space_notes.page.delete_confirm.message'),
    header: t('band_space_notes.page.delete_confirm.header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space_notes.page.delete_confirm.reject'),
    acceptLabel: t('band_space_notes.page.delete_confirm.accept'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await notesStore.deleteNote(bandSpaceId, noteId)
        toast.add({
          severity: 'success',
          summary: t('band_space_notes.page.deleted'),
          life: 3000
        })
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: t('band_space_notes.page.error_summary'),
          detail: error.message || t('band_space_notes.page.delete_error'),
          life: 5000
        })
      }
    }
  })
}
</script>
