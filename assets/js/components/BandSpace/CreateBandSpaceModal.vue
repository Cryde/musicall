<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Créer un Band Space"
    :style="{ width: '25rem' }"
    :closable="!bandSpaceStore.isCreating"
    :closeOnEscape="!bandSpaceStore.isCreating"
  >
    <form @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="bandSpaceName" class="font-medium">Nom du Band Space</label>
          <InputText
            id="bandSpaceName"
            v-model="name"
            placeholder="Ex: Mon groupe de rock"
            :disabled="bandSpaceStore.isCreating"
            class="w-full"
            autofocus
          />
          <small v-if="error" class="text-red-500">{{ error }}</small>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            type="button"
            label="Annuler"
            severity="secondary"
            @click="handleClose"
            :disabled="bandSpaceStore.isCreating"
          />
          <Button
            type="submit"
            label="Créer"
            :loading="bandSpaceStore.isCreating"
            :disabled="!name.trim()"
          />
        </div>
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const emit = defineEmits(['created'])

const bandSpaceStore = useBandSpaceStore()
const toast = useToast()

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

watch(isVisible, (newValue) => {
  if (newValue) {
    name.value = ''
    error.value = ''
  }
})

function handleClose() {
  bandSpaceStore.closeCreateModal()
}

async function handleSubmit() {
  if (!name.value.trim()) {
    error.value = 'Veuillez saisir un nom'
    return
  }

  error.value = ''

  try {
    const newSpace = await bandSpaceStore.createBandSpace(name.value.trim())
    bandSpaceStore.closeCreateModal()
    toast.add({
      severity: 'success',
      summary: 'Band Space créé',
      detail: `"${newSpace.name}" a été créé avec succès`,
      life: 3000
    })
    emit('created', newSpace)
  } catch (e) {
    // Use field-specific error if available, otherwise use aggregated message
    const fieldError = e.violationsByField?.name?.[0]
    error.value = fieldError || e.message

    // Only show toast for non-validation errors (validation errors are shown inline)
    if (!e.isValidationError) {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: e.message,
        life: 5000
      })
    }
  }
}
</script>
