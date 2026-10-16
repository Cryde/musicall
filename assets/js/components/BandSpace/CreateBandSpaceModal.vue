<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('band_space.selector.create')"
    :style="{ width: '25rem' }"
    :closable="!bandSpaceStore.isCreating"
    :closeOnEscape="!bandSpaceStore.isCreating"
  >
    <form @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="bandSpaceName" class="font-medium">{{ t('band_space.create_modal.name_label') }}</label>
          <InputText
            id="bandSpaceName"
            v-model="name"
            :placeholder="t('band_space.create_modal.name_placeholder')"
            :disabled="bandSpaceStore.isCreating"
            class="w-full"
            autofocus
          />
          <small v-if="error" class="text-red-500">{{ error }}</small>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            type="button"
            :label="t('band_space.common.cancel')"
            severity="secondary"
            @click="handleClose"
            :disabled="bandSpaceStore.isCreating"
          />
          <Button
            type="submit"
            :label="t('band_space.create_modal.submit')"
            :loading="bandSpaceStore.isCreating"
            :disabled="!name.trim()"
          />
        </div>
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { openDraftNameStorage, takeDraftName } from '../../utils/bandSpaceDraftName.js'

const emit = defineEmits(['created'])

const bandSpaceStore = useBandSpaceStore()
const toast = useToast()
const { t } = useI18n()

const name = ref('')
const error = ref('')

const isVisible = computed({
  get: () => bandSpaceStore.isCreateModalOpen,
  set: (value) => {
    if (!value) {
      bandSpaceStore.closeCreateModal()
    }
  }
})

// Immediate because /band-space opens the modal before navigating, so it can already be open when
// the band layout mounts this component.
watch(
  isVisible,
  (newValue) => {
    if (newValue) {
      name.value = takeDraftName(openDraftNameStorage())
      error.value = ''
    }
  },
  { immediate: true }
)

function handleClose() {
  bandSpaceStore.closeCreateModal()
}

async function handleSubmit() {
  if (!name.value.trim()) {
    error.value = t('band_space.create_modal.name_required')
    return
  }

  error.value = ''

  try {
    const newSpace = await bandSpaceStore.createBandSpace(name.value.trim())
    bandSpaceStore.closeCreateModal()
    toast.add({
      severity: 'success',
      summary: t('band_space.create_modal.created_summary'),
      detail: t('band_space.create_modal.created_detail', { name: newSpace.name }),
      life: 3000
    })
    emit('created', newSpace)
  } catch (e) {
    // Use field-specific error if available, otherwise use aggregated message
    const fieldError = e.violationsByField?.name?.[0]?.message
    error.value = fieldError || e.message

    // Only show toast for non-validation errors (validation errors are shown inline)
    if (!e.isValidationError) {
      toast.add({
        severity: 'error',
        summary: t('band_space.common.error'),
        detail: e.message,
        life: 5000
      })
    }
  }
}
</script>
