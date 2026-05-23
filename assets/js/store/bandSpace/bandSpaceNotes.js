import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceNotesApi from '../../api/bandSpace/band-space-notes.js'

export const useBandSpaceNotesStore = defineStore('bandSpaceNotes', () => {
  const notes = ref([])
  const selectedNoteId = ref(null)
  const selectedNote = ref(null)
  const isLoading = ref(false)
  const isLoadingNote = ref(false)
  const isSaving = ref(false)
  const isCreating = ref(false)
  const isDeleting = ref(false)
  const saveStatus = ref(null) // 'saving' | 'saved' | 'error'
  const loadError = ref(null)

  // Monotonic tokens to discard stale responses if the user navigates fast
  // (selects another note before the first one loads, or switches bandSpace).
  let notesLoadToken = 0
  let selectedNoteLoadToken = 0

  const tree = computed(() => buildTree(notes.value))

  function buildTree(flatList) {
    const map = new Map()
    const roots = []

    for (const note of flatList) {
      map.set(note.id, {
        key: note.id,
        label: note.title,
        data: note,
        children: [],
        leaf: !note.has_children
      })
    }

    for (const note of flatList) {
      const node = map.get(note.id)
      if (note.parent_id && map.has(note.parent_id)) {
        const parentNode = map.get(note.parent_id)
        parentNode.children.push(node)
        parentNode.leaf = false
      } else {
        roots.push(node)
      }
    }

    return roots
  }

  async function loadNotes(bandSpaceId) {
    const token = ++notesLoadToken
    isLoading.value = true
    loadError.value = null
    notes.value = []
    try {
      const data = await bandSpaceNotesApi.getNotes(bandSpaceId)
      if (token !== notesLoadToken) return
      notes.value = data
    } catch {
      if (token !== notesLoadToken) return
      notes.value = []
      loadError.value = 'Impossible de charger les notes'
    } finally {
      if (token === notesLoadToken) {
        isLoading.value = false
      }
    }
  }

  async function selectNote(bandSpaceId, noteId) {
    if (selectedNoteId.value === noteId && selectedNote.value) {
      return
    }

    const token = ++selectedNoteLoadToken
    selectedNoteId.value = noteId
    selectedNote.value = null
    isLoadingNote.value = true
    loadError.value = null

    try {
      const data = await bandSpaceNotesApi.getNote(bandSpaceId, noteId)
      if (token !== selectedNoteLoadToken) return
      selectedNote.value = data
    } catch {
      if (token !== selectedNoteLoadToken) return
      selectedNote.value = null
      selectedNoteId.value = null
      loadError.value = 'Impossible de charger la note'
    } finally {
      if (token === selectedNoteLoadToken) {
        isLoadingNote.value = false
      }
    }
  }

  async function createNote(bandSpaceId, title, parentId = null) {
    isCreating.value = true
    try {
      const data = { title }
      if (parentId) {
        data.parent_id = parentId
      }
      const newNote = await bandSpaceNotesApi.create(bandSpaceId, data)
      await loadNotes(bandSpaceId)
      return newNote
    } finally {
      isCreating.value = false
    }
  }

  async function updateNoteContent(bandSpaceId, noteId, content) {
    saveStatus.value = 'saving'
    isSaving.value = true
    try {
      const updated = await bandSpaceNotesApi.update(bandSpaceId, noteId, { content })
      if (selectedNote.value && selectedNote.value.id === noteId) {
        selectedNote.value = updated
      }
      saveStatus.value = 'saved'
    } catch {
      saveStatus.value = 'error'
    } finally {
      isSaving.value = false
    }
  }

  async function updateNoteTitle(bandSpaceId, noteId, title) {
    const noteIndex = notes.value.findIndex((n) => n.id === noteId)
    const previousTitle = noteIndex !== -1 ? notes.value[noteIndex].title : null

    if (noteIndex !== -1) {
      notes.value[noteIndex] = { ...notes.value[noteIndex], title }
    }

    try {
      const updated = await bandSpaceNotesApi.update(bandSpaceId, noteId, { title })
      if (selectedNote.value && selectedNote.value.id === noteId) {
        selectedNote.value = updated
      }
    } catch {
      if (noteIndex !== -1 && previousTitle !== null) {
        notes.value[noteIndex] = { ...notes.value[noteIndex], title: previousTitle }
      }
      saveStatus.value = 'error'
    }
  }

  async function updateNoteEmoji(bandSpaceId, noteId, emoji) {
    const noteIndex = notes.value.findIndex((n) => n.id === noteId)
    const previousEmoji = noteIndex !== -1 ? notes.value[noteIndex].emoji : null

    if (noteIndex !== -1) {
      notes.value[noteIndex] = { ...notes.value[noteIndex], emoji }
    }

    try {
      const updated = await bandSpaceNotesApi.update(bandSpaceId, noteId, { emoji })
      if (selectedNote.value && selectedNote.value.id === noteId) {
        selectedNote.value = updated
      }
    } catch {
      if (noteIndex !== -1) {
        notes.value[noteIndex] = { ...notes.value[noteIndex], emoji: previousEmoji }
      }
      saveStatus.value = 'error'
    }
  }

  async function deleteNote(bandSpaceId, noteId) {
    isDeleting.value = true
    try {
      await bandSpaceNotesApi.deleteNote(bandSpaceId, noteId)
      if (selectedNoteId.value === noteId) {
        selectedNoteId.value = null
        selectedNote.value = null
      }
      await loadNotes(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  function clear() {
    notes.value = []
    selectedNoteId.value = null
    selectedNote.value = null
    saveStatus.value = null
    loadError.value = null
  }

  return {
    notes: readonly(notes),
    selectedNoteId: readonly(selectedNoteId),
    selectedNote: readonly(selectedNote),
    isLoading: readonly(isLoading),
    isLoadingNote: readonly(isLoadingNote),
    isSaving: readonly(isSaving),
    isCreating: readonly(isCreating),
    isDeleting: readonly(isDeleting),
    saveStatus: readonly(saveStatus),
    loadError: readonly(loadError),
    tree,
    loadNotes,
    selectNote,
    createNote,
    updateNoteContent,
    updateNoteTitle,
    updateNoteEmoji,
    deleteNote,
    clear
  }
})
