import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceNotesApi from '../../api/bandSpace/band-space-notes.js'
import { saveNoteContentWithRetries } from '../../utils/noteContentSave.js'
import {
  failedSaveNoteIds,
  holdsUnsavedContent,
  noteSaveStatus,
  withNoteSaveStatus,
  withoutNoteSaveStatus
} from '../../utils/noteSaveStatus.js'

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
  // Per note, because a save is fired by a timer and can land long after the member has opened
  // another note: a single store wide status put the previous note's failure on whichever note
  // happened to be open, an error badge on a note nobody had touched.
  const saveStatusByNoteId = ref({}) // noteId -> 'saving' | 'saved' | 'error' | 'conflict'
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

  const saveStatus = computed(() => noteSaveStatus(saveStatusByNoteId.value, selectedNoteId.value))

  const hasUnsavedContent = computed(() => holdsUnsavedContent(saveStatusByNoteId.value))

  function setSaveStatus(noteId, status) {
    saveStatusByNoteId.value = withNoteSaveStatus(saveStatusByNoteId.value, noteId, status)
  }

  function clearSaveStatus(noteId) {
    saveStatusByNoteId.value = withoutNoteSaveStatus(saveStatusByNoteId.value, noteId)
  }

  /**
   * Asks once, and only about text nothing is going to save. A save still on the debounce is
   * deliberately not asked about: leaving the route unmounts the editor, which flushes it, so the
   * question would be about a save already on its way.
   *
   * Returns true when it is safe to carry on, so callers read as
   * `if (!confirmDiscardingEdits()) return`.
   */
  function confirmDiscardingEdits() {
    const unsaved = failedSaveNoteIds(saveStatusByNoteId.value)
    if (unsaved.length === 0) return true

    const confirmed = window.confirm(
      'Des modifications ne sont pas enregistrées et seront perdues. Continuer ?'
    )
    if (confirmed) {
      for (const noteId of unsaved) {
        clearSaveStatus(noteId)
      }
    }

    return confirmed
  }

  function titleOf(noteId) {
    return notes.value.find((note) => note.id === noteId)?.title ?? null
  }

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
    // What comes back is a fresh copy, so whatever the last save of this note answered no longer
    // says anything about it. Above all a leftover conflict would open the note under a blocking
    // banner about an edit nobody has made to it, and the editor only watches for the transition
    // into that state, so it would never clear.
    clearSaveStatus(noteId)

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
   *
   * A failure is retried before it is reported, and always with the same payload, so a retry is
   * refused rather than allowed to overwrite. Nothing else would ever send this text: it was never
   * a member pressing save, so there is nothing for them to press again. What the caller gets back
   * is the outcome, because a note whose save failed on the way out is no longer the one on
   * screen, and a toast is then the only place it can be said.
   *
   * @returns {Promise<{status: 'saved'|'conflict'|'error', noteTitle: string|null}>}
   */
  async function updateNoteContent(bandSpaceId, noteId, content, contentVersion) {
    // Read now rather than at the end: the last save of a session answers after the module has been
    // left, and by then the tree it would have been looked up in has been cleared.
    const noteTitle = titleOf(noteId)

    setSaveStatus(noteId, 'saving')
    isSaving.value = true
    try {
      const outcome = await saveNoteContentWithRetries({
        save: () =>
          bandSpaceNotesApi.update(bandSpaceId, noteId, {
            content,
            expected_content_version: contentVersion
          })
      })

      if (outcome.status === 'saved' && selectedNote.value && selectedNote.value.id === noteId) {
        selectedNote.value = outcome.note
      }
      setSaveStatus(noteId, outcome.status)

      return { status: outcome.status, noteTitle }
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
      clearSaveStatus(noteId)
      selectedNoteReloadCount.value++
    } finally {
      isReloadingNote.value = false
    }
  }

  /**
   * Renaming and picking an emoji are single deliberate acts, so a failure is rolled back and
   * reported rather than left as a status: the save badge belongs to the body, and showing "Erreur"
   * there for a rename that has already been undone points the member at the wrong thing.
   *
   * @returns {Promise<{status: 'saved'|'error'}>}
   */
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

      return { status: 'saved' }
    } catch {
      if (noteIndex !== -1 && previousTitle !== null) {
        notes.value[noteIndex] = { ...notes.value[noteIndex], title: previousTitle }
      }

      return { status: 'error' }
    }
  }

  /** @returns {Promise<{status: 'saved'|'error'}>} */
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

      return { status: 'saved' }
    } catch {
      if (noteIndex !== -1) {
        notes.value[noteIndex] = { ...notes.value[noteIndex], emoji: previousEmoji }
      }

      return { status: 'error' }
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
    saveStatusByNoteId.value = {}
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
    saveStatus,
    hasUnsavedContent,
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
    confirmDiscardingEdits,
    deleteNote,
    clear
  }
})
