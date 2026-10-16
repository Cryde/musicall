<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="parentId ? t('band_space_notes.create_dialog.header_child') : t('band_space_notes.create_dialog.header_root')"
    :style="{ width: '25rem' }"
    :closable="true"
    :closeOnEscape="true"
  >
    <form @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="noteTitle" class="font-medium">{{ t('band_space_notes.create_dialog.title_label') }}</label>
          <InputText
            id="noteTitle"
            v-model="title"
            :placeholder="t('band_space_notes.create_dialog.title_placeholder')"
            class="w-full"
            autofocus
          />
          <small v-if="error" class="text-red-500">{{ error }}</small>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <Button
            type="button"
            :label="t('band_space_notes.create_dialog.cancel')"
            severity="secondary"
            @click="isVisible = false"
          />
          <Button
            type="submit"
            :label="t('band_space_notes.create_dialog.create')"
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
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
    error.value = t('band_space_notes.create_dialog.title_required')
    return
  }

  emit('created', { title: title.value.trim(), parentId: props.parentId })
  isVisible.value = false
}
</script>
