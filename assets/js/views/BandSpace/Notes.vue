<template>
  <div class="flex bg-surface-0 dark:bg-surface-900 rounded-2xl overflow-hidden min-h-[400px] md:min-h-[600px]">
    <!-- Sidebar: always visible on md+, toggle on mobile -->
    <div
      class="border-r border-surface-200 dark:border-surface-700 flex-shrink-0 overflow-hidden"
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
      class="flex-1 flex flex-col"
      :class="mobileView === 'tree' ? 'hidden md:flex' : ''"
    >
      <!-- Mobile back button -->
      <div v-if="mobileView === 'editor'" class="md:hidden flex items-center gap-2 p-3 border-b border-surface-200 dark:border-surface-700">
        <Button
          icon="pi pi-arrow-left"
          aria-label="Retour"
          text
          rounded
          size="small"
          @click="handleBack"
        />
        <span class="text-sm text-surface-500">Notes</span>
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
          Sélectionnez une note pour commencer
        </p>
      </div>
    </div>

    <!-- Create Note Dialog -->
    <CreateNoteDialog
      v-model:visible="showCreateDialog"
      :parentId="createParentId"
      @created="handleCreateNote"
    />

  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import CreateNoteDialog from '../../components/BandSpace/Notes/CreateNoteDialog.vue'
import NoteEditor from '../../components/BandSpace/Notes/NoteEditor.vue'
import NoteTree from '../../components/BandSpace/Notes/NoteTree.vue'
import { useBandSpaceNotesStore } from '../../store/bandSpace/bandSpaceNotes.js'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const notesStore = useBandSpaceNotesStore()
// Wipe any previous space's notes synchronously before first render to avoid
// flashing A's tree when switching to B (the :key on <router-view> remounts
// this view but the Pinia store keeps A's state until cleared).
notesStore.clear()

const showCreateDialog = ref(false)
const createParentId = ref(null)
const mobileView = ref('tree') // 'tree' | 'editor'
const noteTreeRef = ref(null)
// Only one editor is ever mounted, the open note's, so one flag is the whole answer.
const editorHasPendingEdits = ref(false)

const bandSpaceId = route.params.id

onMounted(() => {
  window.addEventListener('beforeunload', warnOnUnload)
  notesStore.loadNotes(bandSpaceId)
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

  if (!notesStore.confirmDiscardingEdits()) return false

  notesStore.selectNote(bandSpaceId, noteId)

  return true
}

function handleSelect(noteId) {
  if (!openNote(noteId)) return

  mobileView.value = 'editor'
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
      summary: 'Note créée',
      life: 3000
    })
    // Created either way, opened only if the note being left has nothing to lose. Same unmount,
    // same loss, so the same question as clicking another note in the tree.
    openNote(newNote.id)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de créer la note',
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
      summary: 'Erreur',
      detail: "Le titre n'a pas pu être modifié",
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
      summary: 'Erreur',
      detail: "L'emoji n'a pas pu être modifié",
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
      summary: 'Erreur',
      detail: error.message || 'Impossible de recharger la note',
      life: 5000
    })
  }
}

function handleDelete(noteId) {
  confirm.require({
    message:
      'Êtes-vous sûr de vouloir supprimer cette note ? Les sous-notes seront également supprimées. Les images qui s’y trouvent resteront dans Fichiers.',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await notesStore.deleteNote(bandSpaceId, noteId)
        toast.add({
          severity: 'success',
          summary: 'Note supprimée',
          life: 3000
        })
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: error.message || 'Impossible de supprimer la note',
          life: 5000
        })
      }
    }
  })
}
</script>
