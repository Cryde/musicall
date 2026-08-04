<template>
  <Dialog
    v-model:visible="visible"
    :header="HEADERS[mode]"
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
        <Button :label="SUBMIT_LABELS[mode]" type="submit" :loading="isSubmitting" />
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
  /** create, rename or duplicate. */
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

const HEADERS = {
  create: 'Nouveau tech rider',
  rename: 'Renommer le tech rider',
  duplicate: 'Dupliquer le tech rider'
}

const SUBMIT_LABELS = {
  create: 'Créer',
  rename: 'Renommer',
  duplicate: 'Dupliquer'
}

const SUCCESS_SUMMARIES = {
  create: 'Tech rider créé',
  rename: 'Tech rider renommé',
  duplicate: 'Tech rider dupliqué'
}

const isRename = computed(() => props.mode === 'rename')
const isDuplicate = computed(() => props.mode === 'duplicate')

watch(visible, (isOpen) => {
  if (!isOpen) return
  // Rename starts from the current name; duplicate starts from the caller's suggestion, which is
  // the source name so the band edits a year rather than retyping the lot.
  name.value = isRename.value || isDuplicate.value ? props.initialName : ''
  violation.value = null
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
      : isDuplicate.value
        ? await techRidersStore.duplicateTechRider(props.bandSpaceId, props.riderId, trimmed)
        : await techRidersStore.createTechRider(props.bandSpaceId, trimmed)
    toast.add({
      severity: 'success',
      summary: SUCCESS_SUMMARIES[props.mode],
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
