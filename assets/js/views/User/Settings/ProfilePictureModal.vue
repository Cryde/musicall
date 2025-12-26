<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Photo de profil"
    :style="{ width: '50rem' }"
    :closable="!userSettingsStore.isChangingPicture"
    :closeOnEscape="!userSettingsStore.isChangingPicture"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex justify-center py-4">
      <Cropper
        ref="cropperRef"
        :src="image"
        :stencil-component="CircleStencil"
        :stencil-props="{ aspectRatio: 1 }"
        :min-height="200"
        :min-width="200"
        class="max-h-96"
      />
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Annuler"
          severity="secondary"
          @click="handleClose"
          :disabled="userSettingsStore.isChangingPicture"
        />
        <Button
          label="Sauvegarder"
          icon="pi pi-check"
          :loading="userSettingsStore.isChangingPicture"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { CircleStencil, Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, ref } from 'vue'
import { useUserSettingsStore } from '../../../store/user/settings.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  image: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:visible', 'saved'])

const userSettingsStore = useUserSettingsStore()

const cropperRef = ref(null)
const error = ref('')

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

function handleClose() {
  error.value = ''
  emit('update:visible', false)
}

async function handleSave() {
  error.value = ''

  const { canvas } = cropperRef.value.getResult()

  if (canvas) {
    canvas.toBlob(async (blob) => {
      const formData = new FormData()
      formData.append('imageFile', blob, 'profile.jpg')

      try {
        await userSettingsStore.changeProfilePicture(formData)
        emit('update:visible', false)
        emit('saved')
      } catch (e) {
        if (e.violations?.length) {
          error.value = e.violations.map(v => v.message).join('. ')
        } else {
          error.value = e.message || 'Une erreur est survenue'
        }
      }
    }, 'image/jpeg', 0.9)
  }
}
</script>
