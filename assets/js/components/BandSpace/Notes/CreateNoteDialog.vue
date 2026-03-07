<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="parentId ? 'Nouvelle sous-note' : 'Nouvelle note'"
    :style="{ width: '25rem' }"
    :closable="true"
    :closeOnEscape="true"
  >
    <form @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="noteTitle" class="font-medium">Titre</label>
          <InputText
            id="noteTitle"
            v-model="title"
            placeholder="Titre de la note"
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
            @click="isVisible = false"
          />
          <Button
            type="submit"
            label="Créer"
            :disabled="!title.trim()"
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
import { ref, watch } from 'vue'

const props = defineProps({
  parentId: { type: String, default: null }
})

const emit = defineEmits(['created'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const title = ref('')
const error = ref('')

watch(isVisible, (newValue) => {
  if (newValue) {
    title.value = ''
    error.value = ''
  }
})

function handleSubmit() {
  if (!title.value.trim()) {
    error.value = 'Veuillez saisir un titre'
    return
  }

  emit('created', { title: title.value.trim(), parentId: props.parentId })
  isVisible.value = false
}
</script>
