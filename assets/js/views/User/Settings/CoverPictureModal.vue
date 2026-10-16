<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('profile.settings.cover_picture.title')"
    :style="{ width: '60rem' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex justify-center py-4">
      <Cropper
        ref="cropperRef"
        :src="image"
        :stencil-props="{ aspectRatio: 4 }"
        :min-height="200"
        :min-width="600"
        class="max-h-96"
      />
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="t('profile.settings.cover_picture.cancel')"
          severity="secondary"
          @click="handleClose"
          :disabled="isSaving"
        />
        <Button
          :label="t('profile.settings.cover_picture.save')"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserProfileStore } from '../../../store/user/profile.js'

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
const { t } = useI18n()

const userProfileStore = useUserProfileStore()

const cropperRef = ref(null)
const error = ref('')
const isSaving = ref(false)

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
  isSaving.value = true

  const { canvas } = cropperRef.value.getResult()

  if (canvas) {
    canvas.toBlob(
      async (blob) => {
        try {
          await userProfileStore.uploadCoverPicture(blob)
          emit('update:visible', false)
          emit('saved')
        } catch (e) {
          if (e.violations?.length) {
            error.value = e.violations.map((v) => v.message).join('. ')
          } else {
            error.value = e.message || t('profile.settings.cover_picture.error')
          }
        } finally {
          isSaving.value = false
        }
      },
      'image/jpeg',
      0.9
    )
  } else {
    isSaving.value = false
  }
}
</script>
