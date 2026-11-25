import { ref } from 'vue'

/**
 * What a service's form dialog does around its fields (#1410, #1411): load as it opens, save and close, and tell the
 * page to refresh once it closes when something was sent at once (a work, a media, a sound).
 *
 * @param {object} options
 * @param {() => Promise<void>} options.load
 * @param {() => Promise<void>} options.save
 * @param {(event: 'saved' | 'changed') => void} options.emit
 * @param {import('vue').Ref<boolean>} options.visible the dialog's model
 */
export function useServiceForm({ load, save, emit, visible }) {
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref('')
  let hasChanges = false

  async function handleShow() {
    isLoading.value = true
    error.value = ''
    try {
      await load()
    } catch (e) {
      error.value = e.message
    } finally {
      isLoading.value = false
    }
  }

  async function handleSave() {
    isSaving.value = true
    error.value = ''
    try {
      await save()
      hasChanges = false
      emit('saved')
      visible.value = false
    } catch (e) {
      error.value = e.message
    } finally {
      isSaving.value = false
    }
  }

  function markChanged() {
    hasChanges = true
  }

  function handleHide() {
    if (hasChanges) emit('changed')
    hasChanges = false
  }

  return { isLoading, isSaving, error, handleShow, handleSave, markChanged, handleHide }
}
