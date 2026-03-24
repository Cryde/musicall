<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="parentId ? 'Nouvelle sous-catégorie' : 'Nouvelle catégorie'"
    :style="{ width: '100%', maxWidth: '25rem' }"
    :closable="true"
    :closeOnEscape="true"
  >
    <form @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="categoryName" class="font-medium">Nom</label>
          <InputText
            id="categoryName"
            v-model="name"
            :placeholder="parentId ? 'Nom de la sous-catégorie' : 'Nom de la catégorie'"
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
import { ref, watch } from 'vue'

const props = defineProps({
  parentId: { type: String, default: null }
})

const emit = defineEmits(['created'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const name = ref('')
const error = ref('')

watch(isVisible, (newValue) => {
  if (newValue) {
    name.value = ''
    error.value = ''
  }
})

function handleSubmit() {
  if (!name.value.trim()) {
    error.value = 'Veuillez saisir un nom'
    return
  }

  emit('created', { name: name.value.trim(), parentId: props.parentId })
  isVisible.value = false
}
</script>
