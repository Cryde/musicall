<template>
  <Dialog
    v-model:visible="visible"
    header="Nouvelle setlist"
    modal
    :style="{ width: '28rem' }"
    @hide="resetForm"
  >
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Nom <span class="text-red-500">*</span></label>
        <InputText
          v-model="name"
          autofocus
          placeholder="ex. Tour d'été 2026"
          class="w-full"
          :invalid="!!violation"
          aria-label="Nom"
        />
        <small v-if="violation" class="text-red-500">{{ violation }}</small>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button label="Annuler" severity="secondary" text type="button" @click="visible = false" />
        <Button label="Créer" type="submit" :loading="isSubmitting" />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const setlistsStore = useBandSetlistsStore()
const toast = useToast()

const name = ref('')
const isSubmitting = ref(false)
const violation = ref(null)

watch(visible, (isOpen) => {
  if (isOpen) {
    name.value = ''
    violation.value = null
  }
})

function resetForm() {
  name.value = ''
  violation.value = null
}

async function handleSubmit() {
  const trimmed = name.value.trim()
  if (!trimmed) {
    violation.value = 'Veuillez spécifier un nom'
    return
  }
  isSubmitting.value = true
  violation.value = null
  try {
    const created = await setlistsStore.createSetlist(props.bandSpaceId, { name: trimmed })
    toast.add({ severity: 'success', summary: 'Setlist créée', life: 3000 })
    emit('created', created)
    visible.value = false
  } catch (e) {
    if (e.isValidationError && e.violationsByField?.name?.[0]?.message) {
      violation.value = e.violationsByField.name[0].message
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>
