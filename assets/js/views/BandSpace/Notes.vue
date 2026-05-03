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

      <NoteEditor
        v-else-if="notesStore.selectedNote"
        :key="notesStore.selectedNoteId"
        :note="notesStore.selectedNote"
        :saveStatus="notesStore.saveStatus"
        @update-content="handleUpdateContent"
        @update-title="handleUpdateTitle"
        @update-emoji="handleUpdateEmoji"
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

    <!-- Delete confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CreateNoteDialog from '../../components/BandSpace/Notes/CreateNoteDialog.vue'
import NoteEditor from '../../components/BandSpace/Notes/NoteEditor.vue'
import NoteTree from '../../components/BandSpace/Notes/NoteTree.vue'
import { useBandSpaceNotesStore } from '../../store/bandSpace/bandSpaceNotes.js'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()
const notesStore = useBandSpaceNotesStore()

const showCreateDialog = ref(false)
const createParentId = ref(null)
const mobileView = ref('tree') // 'tree' | 'editor'
const noteTreeRef = ref(null)

const bandSpaceId = route.params.id

onMounted(() => {
  notesStore.loadNotes(bandSpaceId)
})

onUnmounted(() => {
  notesStore.clear()
})

function handleSelect(noteId) {
  notesStore.selectNote(bandSpaceId, noteId)
  mobileView.value = 'editor'
}

function handleBack() {
  mobileView.value = 'tree'
}

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
    notesStore.selectNote(bandSpaceId, newNote.id)
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de créer la note',
      life: 5000
    })
  }
}

function handleUpdateContent({ noteId, content }) {
  notesStore.updateNoteContent(bandSpaceId, noteId, content)
}

function handleUpdateTitle(title) {
  if (notesStore.selectedNoteId) {
    notesStore.updateNoteTitle(bandSpaceId, notesStore.selectedNoteId, title)
  }
}

function handleUpdateEmoji(emoji) {
  if (notesStore.selectedNoteId) {
    notesStore.updateNoteEmoji(bandSpaceId, notesStore.selectedNoteId, emoji)
  }
}

function handleDelete(noteId) {
  confirm.require({
    message:
      'Êtes-vous sûr de vouloir supprimer cette note ? Les sous-notes seront également supprimées.',
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
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la note',
          life: 5000
        })
      }
    }
  })
}
</script>
