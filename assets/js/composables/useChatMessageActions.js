import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Editing and deleting a message, in the band chat (#966, #967) and in a direct conversation (#1245).
 * The store behind each list does the call; this holds what the screen needs around it.
 *
 * @param {object} options
 * @param {() => ReadonlyArray<object>} options.messages the messages on screen
 * @param {(messageId: string, content: string) => Promise<unknown>} options.edit
 * @param {(messageId: string) => Promise<unknown>} options.remove
 */
export function useChatMessageActions({ messages, edit, remove }) {
  const { t } = useI18n()
  const confirm = useConfirm()
  const toast = useToast()

  const editingId = ref(null)
  /** The stored `@[uuid]` format, which is what the editor reads and writes and what gets sent. */
  const editContent = ref('')
  const isSaving = ref(false)
  const saveError = ref('')

  /**
   * The server sends `editable_content` only on the messages it will accept a PATCH for, so asking it
   * is asking the one authority there is.
   */
  function canEdit(message) {
    return typeof message.editable_content === 'string'
  }

  /**
   * Both sides trimmed, not just the typed one: a contenteditable settles on whatever trailing
   * whitespace the browser wants, so comparing it against an untrimmed stored string would light up
   * « Enregistrer » on a message nobody touched and paint « modifié » for nothing.
   */
  const hasEditChanges = computed(() => {
    if (editingId.value === null) {
      return false
    }

    const trimmed = editContent.value.trim()
    if (trimmed === '') {
      return false
    }

    const held = messages().find((message) => message.id === editingId.value)

    return trimmed !== (held?.editable_content ?? '').trim()
  })

  function startEdit(message) {
    editingId.value = message.id
    editContent.value = message.editable_content
    saveError.value = ''
  }

  function cancelEdit() {
    editingId.value = null
    editContent.value = ''
    saveError.value = ''
  }

  /**
   * The box stays open when the save fails, which is the whole reason this awaits rather than closing
   * on the click: with mentions in it, losing the edit means picking every member again.
   */
  async function saveEdit() {
    if (!hasEditChanges.value || isSaving.value) {
      return
    }

    const savingId = editingId.value
    isSaving.value = true
    saveError.value = ''

    try {
      await edit(savingId, editContent.value.trim())
      // Unless another box was opened meanwhile, in another conversation: that one is not done.
      if (editingId.value === savingId) cancelEdit()
    } catch (e) {
      console.error('Failed to edit the message:', e)
      if (editingId.value === savingId) {
        saveError.value = e.message || t('band_space_chat.message_list.edit_failed')
      }
    } finally {
      isSaving.value = false
    }
  }

  function confirmDelete(message) {
    confirm.require({
      message: t('band_space_chat.message_list.delete_confirm_message'),
      header: t('band_space_chat.message_list.delete_confirm_header'),
      icon: 'pi pi-exclamation-triangle',
      rejectLabel: t('band_space_chat.message_list.delete_confirm_reject'),
      acceptLabel: t('band_space_chat.message_list.delete_confirm_accept'),
      acceptClass: 'p-button-danger',
      accept: () => handleDelete(message)
    })
  }

  /**
   * The failure is shown rather than swallowed: the bubble stays exactly as it was, so without a toast
   * a refused delete looks like a click that did nothing.
   */
  async function handleDelete(message) {
    try {
      await remove(message.id)
    } catch (e) {
      console.error('Failed to delete the message:', e)
      toast.add({
        severity: 'error',
        summary: e.message || t('band_space_chat.message_list.delete_failed'),
        life: 5000
      })
    }
  }

  return {
    editingId,
    editContent,
    isSaving,
    saveError,
    hasEditChanges,
    canEdit,
    startEdit,
    cancelEdit,
    saveEdit,
    confirmDelete
  }
}
