<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Supprimer le dossier"
    :style="{ width: '32rem' }"
    @hide="resetState"
  >
    <div class="flex flex-col gap-4">
      <p class="text-sm">
        Vous êtes sur le point de supprimer
        <span class="font-medium">« {{ folder?.name }} »</span>.
        Choisissez ce qu'il advient des éléments qu'il contient.
      </p>

      <div class="flex flex-col gap-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <RadioButton v-model="strategy" value="move_to_root" name="folder-delete-strategy" />
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium">Déplacer le contenu à la racine</span>
            <span class="text-xs text-surface-500">
              Les sous-dossiers et fichiers sont conservés et remontent au niveau supérieur.
            </span>
          </div>
        </label>

        <label
          v-if="isAdmin"
          class="flex items-start gap-3 cursor-pointer"
        >
          <RadioButton v-model="strategy" value="cascade" name="folder-delete-strategy" />
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-red-600">Tout supprimer</span>
            <span class="text-xs text-surface-500">
              Archive tous les fichiers contenus dans ce dossier et ses sous-dossiers. Réservé aux administrateurs.
            </span>
          </div>
        </label>
      </div>

      <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    </div>

    <template #footer>
      <Button
        label="Annuler"
        severity="secondary"
        text
        :disabled="isSubmitting"
        @click="visible = false"
      />
      <Button
        label="Supprimer"
        severity="danger"
        :loading="isSubmitting"
        @click="handleConfirm"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import { ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  folder: { type: Object, default: null },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['deleted'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const strategy = ref('move_to_root')
const isSubmitting = ref(false)
const globalError = ref(null)

watch(visible, (open) => {
  if (open) {
    strategy.value = 'move_to_root'
    globalError.value = null
  }
})

async function handleConfirm() {
  if (!props.folder) return
  isSubmitting.value = true
  globalError.value = null
  try {
    await filesStore.deleteFolder(props.bandSpaceId, props.folder.id, { strategy: strategy.value })
    emit('deleted', props.folder.id)
    visible.value = false
  } catch (e) {
    globalError.value = e.message
  } finally {
    isSubmitting.value = false
  }
}

function resetState() {
  strategy.value = 'move_to_root'
  isSubmitting.value = false
  globalError.value = null
}
</script>
