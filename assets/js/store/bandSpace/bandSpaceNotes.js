import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceNotesApi from '../../api/bandSpace/band-space-notes.js'

/** What the API answers when the body was written against a revision that is no longer current. */
const HTTP_CONFLICT = 409

export const useBandSpaceNotesStore = defineStore('bandSpaceNotes', () => {
  const notes = ref([])
  const selectedNoteId = ref(null)
  const selectedNote = ref(null)
  const isLoading = ref(false)
  const isLoadingNote = ref(false)
  const isSaving = ref(false)
  const isCreating = ref(false)
  const isDeleting = ref(false)
  const isReloadingNote = ref(false)
  const saveStatus = ref(null) // 'saving' | 'saved' | 'error' | 'conflict'
  const loadError = ref(null)
  // Bumped only by an explicit reload, and part of the editor's key, so the editor is rebuilt
  // around the note that came back. A save must never remount it: that would drop the caret
  // mid-sentence, which is why the revision itself is not in the key.
  const selectedNoteReloadCount = ref(0)

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
    // The save status belongs to the note being left, not to the one being opened. Carrying a
    // conflict across would show the next note a blocking banner about an edit nobody made to it,
    // and the editor only watches for the transition into that state, so it would never clear.
    saveStatus.value = null

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

  /**
   * A body write names the revision the editor was showing, and the API refuses it when another
   * member has written since. Without that precondition this was a blind overwrite of the whole
   * document fired by a timer, so a copy left open could erase minutes of someone else's writing
   * with nobody choosing to save and no history to recover from.
   *
   * Every response carries the new revision, so replacing the open note here is what keeps the
   * following save valid.
   */
  async function updateNoteContent(bandSpaceId, noteId, content, contentVersion) {
    saveStatus.value = 'saving'
    isSaving.value = true
    try {
      const updated = await bandSpaceNotesApi.update(bandSpaceId, noteId, {
        content,
        expected_content_version: contentVersion
      })
      if (selectedNote.value && selectedNote.value.id === noteId) {
        selectedNote.value = updated
      }
      saveStatus.value = 'saved'
    } catch (error) {
      // A conflict belongs to the note that was refused. A save flushed on the way out of a note
      // can land once another one is open, and flagging that one would stop an editor that has
      // nothing to resolve, so it falls back to the plain error state.
      const isStillOpen = selectedNote.value !== null && selectedNote.value.id === noteId
      saveStatus.value = isStillOpen && error.status === HTTP_CONFLICT ? 'conflict' : 'error'
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Replaces the open note with what the server holds. Used after a refused save, once the member
   * has copied their own text out.
   *
   * It deliberately does not raise `isLoadingNote`: that swaps the editor for a spinner, and
   * unmounting the editor before the response is in would throw away the very text the member is
   * trying to keep. On failure nothing on screen changes and the caller reports it.
   */
  async function reloadSelectedNote(bandSpaceId) {
    const noteId = selectedNoteId.value
    if (!noteId) return

    const token = ++selectedNoteLoadToken
    isReloadingNote.value = true
    try {
      const data = await bandSpaceNotesApi.getNote(bandSpaceId, noteId)
      if (token !== selectedNoteLoadToken) return
      selectedNote.value = data
      saveStatus.value = null
      selectedNoteReloadCount.value++
    } finally {
      isReloadingNote.value = false
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
    selectedNoteReloadCount.value = 0
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
    isReloadingNote: readonly(isReloadingNote),
    saveStatus: readonly(saveStatus),
    loadError: readonly(loadError),
    selectedNoteReloadCount: readonly(selectedNoteReloadCount),
    tree,
    loadNotes,
    selectNote,
    reloadSelectedNote,
    createNote,
    updateNoteContent,
    updateNoteTitle,
    updateNoteEmoji,
    deleteNote,
    clear
  }
})
