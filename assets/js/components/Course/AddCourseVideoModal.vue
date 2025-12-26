<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Ajouter un cours en vidéo"
    :style="{ width: '35rem' }"
    :closable="!videoStore.isLoadingAdd"
    :closeOnEscape="!videoStore.isLoadingAdd"
  >
    <Message severity="success" :closable="false" class="mb-4">
      Partagez à la communauté vos cours vidéos préférés ou que vous avez créés.<br />
      Pour l'instant seuls les liens Youtube fonctionnent.
    </Message>

    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <Select
          v-model="selectedCategory"
          :options="categories"
          optionLabel="title"
          placeholder="Choisissez une catégorie"
          :disabled="videoStore.isLoadingPreview || videoStore.isLoadingAdd"
          class="w-full"
        />
        <small v-if="errors.category" class="text-red-500">{{ errors.category }}</small>
        <small v-else class="text-surface-500">Sélectionnez la catégorie du cours</small>
      </div>

      <div class="flex flex-col gap-1">
        <InputText
          id="videoUrl"
          v-model="videoUrl"
          placeholder="Url de la vidéo Youtube"
          :disabled="videoStore.isLoadingPreview || videoStore.isLoadingAdd"
          class="w-full"
        />
        <small v-if="!videoStore.isLoadingPreview" class="text-surface-500">
          Avec ce lien nous pourrons récupérer quelques informations
        </small>
        <small v-else class="text-surface-500 flex items-center gap-2">
          <i class="pi pi-spin pi-spinner"></i>
          Chargement de l'aperçu...
        </small>
      </div>

      <Message v-if="isExistingVideo" severity="error" :closable="false">
        La vidéo existe déjà.<br />
        Vous ne pouvez pas la remettre en ligne une nouvelle fois.
      </Message>

      <Message v-if="hasGenericError" severity="error" :closable="false">
        Une erreur est survenue. Veuillez vérifier l'URL et réessayer.
      </Message>

      <template v-if="showPreview && !isExistingVideo">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <InputText
              id="videoTitle"
              v-model="videoTitle"
              placeholder="Titre de la vidéo"
              :disabled="videoStore.isLoadingAdd"
              class="w-full"
            />
            <small v-if="errors.title" class="text-red-500">{{ errors.title }}</small>
            <small v-else class="text-surface-500">Le titre de la vidéo. Vous pouvez le changer</small>
          </div>

          <div class="flex flex-col gap-1">
            <Textarea
              id="videoDescription"
              v-model="videoDescription"
              placeholder="Description de la vidéo"
              :disabled="videoStore.isLoadingAdd"
              rows="4"
              class="w-full"
            />
            <small v-if="errors.description" class="text-red-500">{{ errors.description }}</small>
            <small v-else class="text-surface-500">La description de la vidéo. Vous pouvez la changer</small>
          </div>

          <div v-if="videoImage">
            <img :src="videoImage" alt="Aperçu de la vidéo" class="w-full rounded-lg" />
          </div>
        </div>
      </template>

      <div class="flex justify-end gap-2 mt-4">
        <Button
          type="button"
          label="Annuler"
          severity="secondary"
          @click="handleClose"
          :disabled="videoStore.isLoadingAdd"
        />
        <Button
          type="button"
          label="Publier"
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
import { useDebounceFn } from '@vueuse/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useVideoStore } from '../../store/publication/video.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  categories: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:visible', 'published'])

const videoStore = useVideoStore()
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

const canPublish = computed(() => {
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
    const urlViolation = error.violationsByField?.url?.[0]

    if (urlViolation && urlViolation.includes('existe déjà')) {
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

  if (trimmedUrl.startsWith('https://') && trimmedUrl.includes('youtube')) {
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
    errors.value.category = 'Veuillez sélectionner une catégorie'
    return
  }

  try {
    await videoStore.addVideo({
      url: videoUrl.value.trim(),
      title: videoTitle.value.trim(),
      description: videoDescription.value.trim(),
      categoryId: selectedCategory.value.id
    })

    emit('update:visible', false)

    toast.add({
      severity: 'success',
      summary: 'Cours publié',
      detail: 'Votre cours vidéo a été mis en ligne',
      life: 5000
    })

    emit('published')
  } catch (error) {
    if (error.isValidationError && error.violationsByField) {
      errors.value = {
        title: error.violationsByField.title?.[0],
        description: error.violationsByField.description?.[0],
        url: error.violationsByField.url?.[0]
      }

      if (error.violationsByField.url) {
        hasGenericError.value = true
      }
    } else {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: error.message,
        life: 5000
      })
    }
  }
}
</script>
