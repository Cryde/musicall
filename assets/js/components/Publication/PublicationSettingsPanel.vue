<template>
  <aside class="flex flex-col gap-5 bg-surface-0 dark:bg-surface-800 rounded-md p-4 border border-surface-200 dark:border-surface-700 lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-surface-700 dark:text-surface-200">
      Paramètres
    </h2>

    <div class="grid grid-cols-2 gap-3 text-center bg-surface-50 dark:bg-surface-700/50 rounded-md py-3">
      <div class="flex flex-col">
        <span class="text-lg font-semibold text-surface-900 dark:text-surface-0 tabular-nums">{{ wordCount }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400">{{ wordCount === 1 ? 'mot' : 'mots' }}</span>
      </div>
      <div class="flex flex-col">
        <span class="text-lg font-semibold text-surface-900 dark:text-surface-0 tabular-nums">{{ readingTime }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400">{{ readingTime <= 1 ? 'min de lecture' : 'min de lecture' }}</span>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-category" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Catégorie
      </label>
      <Select
        id="panel-category"
        v-model="categoryId"
        :options="publicationsStore.publicationCategories"
        optionLabel="title"
        optionValue="id"
        placeholder="Choisir une catégorie"
        class="w-full"
        :disabled="disabled"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-description" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Description courte
      </label>
      <Textarea
        id="panel-description"
        v-model="shortDescription"
        placeholder="Cette description apparaîtra sur la page d'accueil"
        rows="3"
        class="w-full"
        :disabled="disabled"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-tags" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Tags
      </label>
      <AutoComplete
        id="panel-tags"
        v-model="tags"
        :suggestions="tagSuggestions"
        multiple
        fluid
        :disabled="disabled"
        placeholder="Ajoutez des tags"
        @complete="handleTagSearch"
        @keydown.enter.prevent="handleTagEnter"
      />
      <small class="text-surface-500 text-xs">
        Genre, format, thème. Entrée pour valider.
      </small>
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Image de couverture
      </label>

      <div v-if="coverUrl" class="relative inline-block">
        <img
          :src="coverUrl"
          alt="Image de couverture"
          class="w-full rounded-lg border border-surface-200 dark:border-surface-700"
        />
        <Button
          icon="pi pi-times"
          aria-label="Retirer la couverture"
          severity="danger"
          size="small"
          rounded
          class="absolute -top-2 -right-2"
          :disabled="disabled"
          @click="handleRemoveCover"
        />
      </div>

      <div v-else class="p-4 border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-lg text-center">
        <i class="pi pi-image text-2xl text-surface-400 mb-1" />
        <p class="text-surface-500 dark:text-surface-400 text-xs">
          Aucune image de couverture
        </p>
      </div>

      <FileUpload
        ref="fileUploadRef"
        mode="basic"
        accept="image/*"
        :maxFileSize="4000000"
        :chooseLabel="coverUrl ? 'Remplacer' : 'Choisir une image'"
        :auto="true"
        :disabled="disabled || publicationEditStore.isUploading"
        customUpload
        @uploader="handleCoverUpload"
      />

      <ProgressBar
        v-if="publicationEditStore.isUploading"
        :value="publicationEditStore.uploadProgress"
        :showValue="true"
        class="mt-1"
      />

      <small class="text-surface-500 text-xs">
        Max 4 Mo, 4000x4000 px
      </small>
    </div>
  </aside>
</template>

<script setup>
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import tagsApi from '../../api/publication/tags.js'
import { usePublicationEditStore } from '../../store/publication/publicationEdit.js'
import { usePublicationsStore } from '../../store/publication/publications.js'

defineProps({
  disabled: { type: Boolean, default: false },
  wordCount: { type: Number, default: 0 },
  readingTime: { type: Number, default: 0 }
})

const shortDescription = defineModel('shortDescription', { type: String, default: '' })
const categoryId = defineModel('categoryId', { type: Number, default: null })
const tags = defineModel('tags', { type: Array, default: () => [] })

const publicationEditStore = usePublicationEditStore()
const publicationsStore = usePublicationsStore()
const toast = useToast()

const tagSuggestions = ref([])
const fileUploadRef = ref(null)

const coverUrl = computed(() => publicationEditStore.publication?.cover_url || null)

onMounted(async () => {
  if (publicationsStore.publicationCategories.length === 0) {
    await publicationsStore.loadCategories()
  }
})

async function handleTagSearch(event) {
  const query = (event.query ?? '').trim()
  if (query.length === 0) {
    tagSuggestions.value = []
    return
  }
  try {
    const items = await tagsApi.search(query)
    tagSuggestions.value = items
      .map((item) => item.label)
      .filter((label) => !tags.value.includes(label))
  } catch (e) {
    console.error('Tag suggestions failed:', e)
    tagSuggestions.value = []
  }
}

function handleTagEnter(event) {
  const raw = (event.target.value ?? '').trim()
  if (raw.length === 0) return
  if (!tags.value.includes(raw)) {
    tags.value = [...tags.value, raw]
  }
  event.target.value = ''
  tagSuggestions.value = []
}

async function handleCoverUpload(event) {
  const file = event.files?.[0]
  if (!file) return

  const uri = await publicationEditStore.uploadCover(file)
  if (!uri) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'envoyer l'image",
      life: 3000
    })
  }
  fileUploadRef.value?.clear()
}

async function handleRemoveCover() {
  const success = await publicationEditStore.removeCover()
  if (!success) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible de supprimer l'image",
      life: 3000
    })
  }
}
</script>
