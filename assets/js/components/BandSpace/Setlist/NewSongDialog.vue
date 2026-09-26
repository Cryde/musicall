<template>
  <Dialog v-model:visible="visible" header="Nouveau titre" modal :style="{ width: 'min(26rem, 100vw)' }" @hide="reset">
    <form class="flex flex-col gap-3" @submit.prevent="submit">
      <div class="flex flex-col gap-1">
        <label for="new-song-title" class="text-sm font-medium">Titre</label>
        <InputText
          id="new-song-title"
          v-model="title"
          autofocus
          :invalid="!!error"
          :aria-describedby="error ? 'new-song-title-error' : 'new-song-title-hint'"
        />
        <small v-if="error" id="new-song-title-error" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</small>
        <small v-else id="new-song-title-hint" class="text-surface-600 dark:text-surface-300">
          Tonalité, BPM, durée et paroles se renseignent ensuite, dans sa fiche.
        </small>
      </div>
      <div class="flex justify-end gap-2">
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
import { ref } from 'vue'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'

/**
 * A song starts from its title alone (#1067); the rest is filled in its drawer, which the caller
 * opens with the song this emits.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const songsStore = useBandSongsStore()
const title = ref('')
const error = ref(null)
const isSubmitting = ref(false)

function reset() {
  title.value = ''
  error.value = null
}

async function submit() {
  const trimmed = title.value.trim()
  if (!trimmed) {
    error.value = 'Indiquez un titre.'
    return
  }
  isSubmitting.value = true
  try {
    const created = await songsStore.createSong(props.bandSpaceId, { title: trimmed })
    visible.value = false
    emit('created', created)
  } catch (e) {
    error.value = e.message
  } finally {
    isSubmitting.value = false
  }
}
</script>
