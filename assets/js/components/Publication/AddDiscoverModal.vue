<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('home.add_discover.header')"
    :style="{ width: '35rem' }"
    :closable="!videoStore.isLoadingAdd"
    :closeOnEscape="!videoStore.isLoadingAdd"
  >
    <Message severity="success" :closable="false" class="mb-4">
      {{ t('home.add_discover.intro_share') }}<br />
      {{ t('home.add_discover.intro_youtube_only') }}
    </Message>

    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <InputText
          id="videoUrl"
          v-model="videoUrl"
          :placeholder="t('home.add_discover.url_placeholder')"
          :disabled="videoStore.isLoadingPreview || videoStore.isLoadingAdd"
          class="w-full"
        />
        <small v-if="!videoStore.isLoadingPreview" class="text-surface-500">
          {{ t('home.add_discover.url_hint') }}
        </small>
        <small v-else class="text-surface-500 flex items-center gap-2">
          <i class="pi pi-spin pi-spinner"></i>
          {{ t('home.add_discover.loading_preview') }}
        </small>
      </div>

      <Message v-if="isExistingVideo" severity="error" :closable="false">
        {{ t('home.add_discover.existing') }}<br />
        {{ t('home.add_discover.existing_detail') }}
      </Message>

      <Message v-if="hasGenericError" severity="error" :closable="false">
        {{ t('home.add_discover.generic_error') }}
      </Message>

      <template v-if="showPreview && !isExistingVideo">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <InputText
              id="videoTitle"
              v-model="videoTitle"
              :placeholder="t('home.add_discover.title_placeholder')"
              :disabled="videoStore.isLoadingAdd"
              class="w-full"
            />
            <small v-if="errors.title" class="text-red-500">{{ errors.title }}</small>
            <small v-else class="text-surface-500">{{ t('home.add_discover.title_hint') }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <Textarea
              id="videoDescription"
              v-model="videoDescription"
              :placeholder="t('home.add_discover.description_placeholder')"
              :disabled="videoStore.isLoadingAdd"
              rows="4"
              class="w-full"
            />
            <small v-if="errors.description" class="text-red-500">{{ errors.description }}</small>
            <small v-else class="text-surface-500">{{ t('home.add_discover.description_hint') }}</small>
          </div>

          <div v-if="videoImage">
            <img :src="videoImage" :alt="t('home.add_discover.preview_alt')" class="w-full rounded-lg" />
          </div>
        </div>
      </template>

      <div class="flex justify-end gap-2 mt-4">
        <Button
          type="button"
          :label="t('home.add_discover.cancel')"
          severity="secondary"
          @click="handleClose"
          :disabled="videoStore.isLoadingAdd"
        />
        <Button
          type="button"
          :label="t('home.add_discover.publish')"
          icon="pi pi-check"
          :loading="videoStore.isLoadingAdd"
          :disabled="!canPublish"
          @click="handlePublish"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useDebounceFn } from '@vueuse/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ERROR_CODES } from '../../constants/errorCodes.js'
import { useVideoStore } from '../../store/publication/video.js'

const emit = defineEmits(['published'])

const { t } = useI18n()
const videoStore = useVideoStore()
const toast = useToast()

const videoUrl = ref('')
const videoTitle = ref('')
const videoDescription = ref('')
const videoImage = ref('')
const previousVideoUrl = ref('')
const showPreview = ref(false)
const isExistingVideo = ref(false)
const hasGenericError = ref(false)
const errors = ref({})

const isVisible = computed({
  get: () => videoStore.isModalOpen,
  set: (value) => {
    if (!value) {
      videoStore.closeModal()
    }
  }
})

const isValidYoutubeUrl = computed(() => {
  const url = videoUrl.value.trim()
  return url.startsWith('https://') && (url.includes('youtube') || url.includes('youtu.be'))
})

const canPublish = computed(() => {
  return (
    showPreview.value &&
    !isExistingVideo.value &&
    isValidYoutubeUrl.value &&
    videoTitle.value.trim().length >= 3 &&
    videoDescription.value.trim().length >= 20
  )
})

watch(isVisible, (newValue) => {
  if (newValue) {
    resetForm()
  }
})

const debouncedFetchPreview = useDebounceFn(async (url) => {
  if (!url || previousVideoUrl.value === url) {
    return
  }

  previousVideoUrl.value = url
  showPreview.value = false
  isExistingVideo.value = false
  hasGenericError.value = false
  errors.value = {}

  try {
    await videoStore.fetchPreview(url)

    if (videoStore.preview) {
      videoTitle.value = videoStore.preview.title || ''
      videoDescription.value = videoStore.preview.description || ''
      videoImage.value = videoStore.preview.image_url || ''
      showPreview.value = true
    }
  } catch (error) {
    const urlViolations = error.violationsByField?.url || []
    const hasExistingVideoError = urlViolations.some((v) => v.code === ERROR_CODES.EXISTING_VIDEO)

    if (hasExistingVideoError) {
      isExistingVideo.value = true
    } else {
      hasGenericError.value = true
    }
  }
}, 500)

watch(videoUrl, (newUrl) => {
  const trimmedUrl = newUrl.trim()

  if (!trimmedUrl) {
    showPreview.value = false
    isExistingVideo.value = false
    hasGenericError.value = false
    previousVideoUrl.value = ''
    return
  }

  // Auto-prepend https:// if user pastes a URL without scheme
  if (
    !trimmedUrl.match(/^https?:\/\//) &&
    (trimmedUrl.includes('youtube') || trimmedUrl.includes('youtu.be'))
  ) {
    videoUrl.value = `https://${trimmedUrl}`
    return
  }

  if (isValidYoutubeUrl.value) {
    debouncedFetchPreview(trimmedUrl)
  }
})

function resetForm() {
  videoUrl.value = ''
  videoTitle.value = ''
  videoDescription.value = ''
  videoImage.value = ''
  previousVideoUrl.value = ''
  showPreview.value = false
  isExistingVideo.value = false
  hasGenericError.value = false
  errors.value = {}
}

function handleClose() {
  videoStore.closeModal()
}

async function handlePublish() {
  errors.value = {}

  try {
    await videoStore.addVideo({
      url: videoUrl.value.trim(),
      title: videoTitle.value.trim(),
      description: videoDescription.value.trim()
    })

    trackUmamiEvent('discover-video-submit')
    emit('published')

    videoStore.closeModal()

    toast.add({
      severity: 'success',
      summary: t('home.add_discover.published_summary'),
      detail: t('home.add_discover.published_detail'),
      life: 5000
    })
  } catch (error) {
    if (error.isValidationError && error.violationsByField) {
      errors.value = {
        title: error.violationsByField.title?.[0]?.message,
        description: error.violationsByField.description?.[0]?.message,
        url: error.violationsByField.url?.[0]?.message
      }

      if (error.violationsByField.url) {
        hasGenericError.value = true
      }
    } else {
      toast.add({
        severity: 'error',
        summary: t('home.add_discover.error_summary'),
        detail: error.message,
        life: 5000
      })
    }
  }
}
</script>
