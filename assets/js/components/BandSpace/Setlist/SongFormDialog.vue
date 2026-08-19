<template>
  <Dialog
    v-model:visible="visible"
    :header="isEdit ? 'Modifier le titre' : 'Ajouter un titre'"
    modal
    :style="{ width: '32rem' }"
    @hide="resetForm"
  >
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Titre <span class="text-red-500">*</span></label>
        <InputText v-model="form.title" autofocus class="w-full" :invalid="!!violationFor('title')" aria-label="Titre" />
        <small v-if="violationFor('title')" class="text-red-500">{{ violationFor('title') }}</small>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Tonalité</label>
          <InputText v-model="form.tonality" placeholder="ex. Em" class="w-full" :invalid="!!violationFor('tonality')" aria-label="Tonalité" />
          <small v-if="violationFor('tonality')" class="text-red-500">{{ violationFor('tonality') }}</small>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">BPM</label>
          <InputNumber
            v-model="form.tempo"
            :min="1"
            :max="400"
            :useGrouping="false"
            class="w-full"
            inputClass="w-full"
            :invalid="!!violationFor('tempo')"
            aria-label="BPM"
          />
          <small v-if="violationFor('tempo')" class="text-red-500">{{ violationFor('tempo') }}</small>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Durée de référence (mm:ss)</label>
        <InputText
          v-model="durationInput"
          placeholder="3:47"
          class="w-full"
          :invalid="!!durationError || !!violationFor('reference_duration')"
          aria-label="Durée de référence au format mm:ss"
        />
        <small v-if="durationError" class="text-red-500">{{ durationError }}</small>
        <small v-else-if="violationFor('reference_duration')" class="text-red-500">{{ violationFor('reference_duration') }}</small>
        <small v-else class="text-surface-500">Minutes et secondes, par exemple 3:47.</small>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Notes</label>
        <Textarea v-model="form.notes" rows="3" class="w-full" aria-label="Notes" />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button label="Annuler" severity="secondary" text type="button" @click="visible = false" />
        <Button :label="isEdit ? 'Enregistrer' : 'Ajouter'" type="submit" :loading="isSubmitting" />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { formatDuration, parseDurationInput } from '../../../utils/setlistDuration.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, default: null }
})

const emit = defineEmits(['saved'])
const visible = defineModel('visible', { type: Boolean, default: false })

const songsStore = useBandSongsStore()
const toast = useToast()

const form = ref({ title: '', tonality: null, tempo: null, notes: null })
// Held as text, not as seconds: the field is a duration a member reads and types, and the
// conversion to the stored unit happens once, on submit.
const durationInput = ref('')
const durationError = ref(null)
const isSubmitting = ref(false)
const violations = ref({})

const isEdit = computed(() => props.song !== null)

watch(
  [visible, () => props.song],
  ([isVisible, song]) => {
    if (isVisible) {
      form.value = song
        ? {
            title: song.title,
            tonality: song.tonality,
            tempo: song.tempo,
            notes: song.notes
          }
        : { title: '', tonality: null, tempo: null, notes: null }
      durationInput.value = song ? formatDuration(song.reference_duration) : ''
      durationError.value = null
      violations.value = {}
    }
  },
  { immediate: true }
)

function violationFor(field) {
  return violations.value[field] ?? null
}

function resetForm() {
  form.value = { title: '', tonality: null, tempo: null, notes: null }
  durationInput.value = ''
  durationError.value = null
  violations.value = {}
}

async function handleSubmit() {
  const duration = parseDurationInput(durationInput.value)
  durationError.value = duration.error
  if (duration.error) return

  isSubmitting.value = true
  violations.value = {}
  try {
    const payload = {
      title: form.value.title?.trim() ?? '',
      tonality: form.value.tonality?.trim() || null,
      tempo: form.value.tempo ?? null,
      reference_duration: duration.seconds,
      notes: form.value.notes?.trim() || null
    }
    if (isEdit.value) {
      await songsStore.updateSong(props.bandSpaceId, props.song.id, payload)
      toast.add({ severity: 'success', summary: 'Titre mis à jour', life: 3000 })
    } else {
      await songsStore.createSong(props.bandSpaceId, payload)
      toast.add({ severity: 'success', summary: 'Titre ajouté', life: 3000 })
    }
    emit('saved')
    visible.value = false
  } catch (e) {
    if (e.isValidationError && e.violationsByField) {
      // violationsByField[field] is an array of {message, code} - take the
      // first message per field for inline display under each input.
      violations.value = Object.fromEntries(
        Object.entries(e.violationsByField).map(([field, list]) => [
          field,
          list?.[0]?.message ?? null
        ])
      )
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>
