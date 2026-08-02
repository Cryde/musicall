<template>
  <Dialog
    v-model:visible="visible"
    :header="isRename ? 'Renommer le tech rider' : 'Nouveau tech rider'"
    modal
    :style="{ width: '28rem' }"
    @hide="resetForm"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div>
        <label for="techRiderName" class="block text-sm font-medium mb-1">
          Nom <span class="text-red-600 dark:text-red-400">*</span>
        </label>
        <InputText
          id="techRiderName"
          v-model="name"
          autofocus
          placeholder="ex. Tech rider 2026"
          class="w-full"
          :invalid="!!violation"
          :aria-describedby="violation ? 'techRiderNameError' : undefined"
        />
        <small v-if="violation" id="techRiderNameError" role="alert" class="text-red-600 dark:text-red-400">
          {{ violation }}
        </small>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button label="Annuler" severity="secondary" text type="button" @click="visible = false" />
        <Button :label="isRename ? 'Renommer' : 'Créer'" type="submit" :loading="isSubmitting" />
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
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  mode: { type: String, default: 'create' },
  riderId: { type: String, default: null },
  initialName: { type: String, default: '' }
})

const emit = defineEmits(['saved'])
const visible = defineModel('visible', { type: Boolean, default: false })

const techRidersStore = useBandTechRidersStore()
const toast = useToast()

const name = ref('')
const isSubmitting = ref(false)
const violation = ref(null)

const isRename = computed(() => props.mode === 'rename')

watch(visible, (isOpen) => {
  if (isOpen) {
    name.value = isRename.value ? props.initialName : ''
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
    const saved = isRename.value
      ? await techRidersStore.renameTechRider(props.bandSpaceId, props.riderId, trimmed)
      : await techRidersStore.createTechRider(props.bandSpaceId, trimmed)
    toast.add({
      severity: 'success',
      summary: isRename.value ? 'Tech rider renommé' : 'Tech rider créé',
      life: 3000
    })
    emit('saved', saved)
    visible.value = false
  } catch (e) {
    // A name violation belongs next to the field; anything else is not about this input.
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
