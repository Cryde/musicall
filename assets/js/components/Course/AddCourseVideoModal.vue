<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('publication.course.add_modal.header')"
    :style="{ width: '35rem' }"
    :closable="!videoStore.isLoadingAdd"
    :closeOnEscape="!videoStore.isLoadingAdd"
  >
    <Message severity="success" :closable="false" class="mb-4">
      {{ t('publication.course.add_modal.intro_share') }}<br />
      {{ t('publication.course.add_modal.intro_youtube_only') }}
    </Message>

    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <Select
          v-model="selectedCategory"
          :options="categories"
          optionLabel="title"
          :placeholder="t('publication.course.add_modal.category_placeholder')"
          :disabled="videoStore.isLoadingPreview || videoStore.isLoadingAdd"
          class="w-full"
        />
        <small v-if="errors.category" class="text-red-500">{{ errors.category }}</small>
        <small v-else class="text-surface-500">{{ t('publication.course.add_modal.category_help') }}</small>
      </div>

      <div class="flex flex-col gap-1">
        <InputText
          id="videoUrl"
          v-model="videoUrl"
          :placeholder="t('publication.course.add_modal.url_placeholder')"
          :disabled="videoStore.isLoadingPreview || videoStore.isLoadingAdd"
          class="w-full"
        />
        <small v-if="isVisitor" class="text-surface-500">
          {{ t('publication.pending.video_preview_sign_in') }}
        </small>
        <small v-else-if="!videoStore.isLoadingPreview" class="text-surface-500">
          {{ t('publication.course.add_modal.url_help') }}
        </small>
        <small v-else class="text-surface-500 flex items-center gap-2">
          <i class="pi pi-spin pi-spinner"></i>
          {{ t('publication.course.add_modal.loading_preview') }}
        </small>
      </div>

      <Message v-if="isExistingVideo" severity="error" :closable="false">
        {{ t('publication.course.add_modal.existing_video') }}<br />
        {{ t('publication.course.add_modal.existing_video_help') }}
      </Message>

      <Message v-if="hasGenericError" severity="error" :closable="false">
        {{ t('publication.course.add_modal.generic_error') }}
      </Message>

      <template v-if="showPreview && !isExistingVideo">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <InputText
              id="videoTitle"
              v-model="videoTitle"
              :placeholder="t('publication.course.add_modal.title_placeholder')"
              :disabled="videoStore.isLoadingAdd"
              class="w-full"
            />
            <small v-if="errors.title" class="text-red-500">{{ errors.title }}</small>
            <small v-else class="text-surface-500">{{ t('publication.course.add_modal.title_help') }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <Textarea
              id="videoDescription"
              v-model="videoDescription"
              :placeholder="t('publication.course.add_modal.description_placeholder')"
              :disabled="videoStore.isLoadingAdd"
              rows="4"
              class="w-full"
            />
            <small v-if="errors.description" class="text-red-500">{{ errors.description }}</small>
            <small v-else class="text-surface-500">{{ t('publication.course.add_modal.description_help') }}</small>
          </div>

          <div v-if="videoImage">
            <img :src="videoImage" :alt="t('publication.course.add_modal.preview_alt')" class="w-full rounded-lg" />
          </div>
        </div>
      </template>

      <div class="flex justify-end gap-2 mt-4">
        <Button
          type="button"
          :label="t('publication.common.cancel')"
          severity="secondary"
          @click="handleClose"
          :disabled="videoStore.isLoadingAdd"
        />
        <Button
          type="button"
          :label="t('publication.course.add_modal.publish')"
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
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ERROR_CODES } from '../../constants/errorCodes.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  categories: {
    type: Array,
    default: () => []
  },
  /** What a visitor filled in before signing in (#1305): the link is previewed once the dialog opens. */
  initialUrl: {
    type: String,
    default: ''
  },
  initialCategory: {
    type: Object,
    default: null
  }
})

// `sign-in-required` carries the link and category when a visitor clicks « Publier »: the preview, and so the rest
// of the form, needs an account, and its 401 would send them to the login page, losing the link (#1305).
const emit = defineEmits(['update:visible', 'published', 'sign-in-required'])

const { t } = useI18n()
const videoStore = useVideoStore()
const userSecurityStore = useUserSecurityStore()
const toast = useToast()

const selectedCategory = ref(null)
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
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const isVisitor = computed(
  () => !userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated
)

const isValidYoutubeUrl = computed(() => {
  const url = videoUrl.value.trim()
  return url.startsWith('https://') && (url.includes('youtube') || url.includes('youtu.be'))
})

// Not while the session is checked: a visitor's click would reach the API, and its 401 would lose the link.
const canPublish = computed(() => {
  if (userSecurityStore.isAuthenticatedLoading) return false
  if (isVisitor.value) return selectedCategory.value !== null && isValidYoutubeUrl.value
  return (
    showPreview.value &&
    !isExistingVideo.value &&
    selectedCategory.value !== null &&
    videoUrl.value.startsWith('https://') &&
    videoTitle.value.trim().length >= 3 &&
    videoDescription.value.trim().length >= 20
  )
})

watch(isVisible, (newValue) => {
  if (newValue) {
    resetForm()
    selectedCategory.value = props.initialCategory
    videoUrl.value = props.initialUrl
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

  if (isValidYoutubeUrl.value && !isVisitor.value) {
    debouncedFetchPreview(trimmedUrl)
  }
})

function resetForm() {
  selectedCategory.value = null
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
  emit('update:visible', false)
}

async function handlePublish() {
  errors.value = {}

  if (!selectedCategory.value) {
    errors.value.category = t('publication.course.add_modal.category_required')
    return
  }
  if (isVisitor.value) {
    emit('sign-in-required', { url: videoUrl.value.trim(), categoryId: selectedCategory.value.id })
    return
  }

  try {
    await videoStore.addVideo({
      url: videoUrl.value.trim(),
      title: videoTitle.value.trim(),
      description: videoDescription.value.trim(),
      categoryId: selectedCategory.value.id
    })

    trackUmamiEvent('course-video-submit', { category: selectedCategory.value.title })
    emit('update:visible', false)

    toast.add({
      severity: 'success',
      summary: t('publication.course.add_modal.published'),
      detail: t('publication.course.add_modal.published_detail'),
      life: 5000
    })

    emit('published')
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
        summary: t('publication.common.error'),
        detail: error.message,
        life: 5000
      })
    }
  }
}
</script>
