<template>
  <aside class="flex flex-col gap-5 bg-surface-0 dark:bg-surface-800 rounded-md p-4 border border-surface-200 dark:border-surface-700 lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-surface-700 dark:text-surface-200">
      {{ t('publication.editor.settings.title') }}
    </h2>

    <div class="grid grid-cols-2 gap-3 text-center bg-surface-50 dark:bg-surface-700/50 rounded-md py-3">
      <div class="flex flex-col">
        <span class="text-lg font-semibold text-surface-900 dark:text-surface-0 tabular-nums">{{ wordCount }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('publication.editor.settings.words', wordCount) }}</span>
      </div>
      <div class="flex flex-col">
        <span class="text-lg font-semibold text-surface-900 dark:text-surface-0 tabular-nums">{{ readingTime }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('publication.editor.settings.reading_time') }}</span>
      </div>
    </div>

    <div v-if="offersLanguageChoice(ENABLED_LOCALES)" class="flex flex-col gap-2">
      <label for="panel-language" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('publication.editor.settings.language') }}
      </label>
      <Select
        id="panel-language"
        v-model="language"
        :options="languageChoices"
        optionLabel="label"
        optionValue="value"
        optionDisabled="disabled"
        class="w-full"
        :disabled="disabled"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-category" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('publication.common.category') }}
      </label>
      <Select
        id="panel-category"
        v-model="categoryId"
        :options="publicationsStore.publicationCategories"
        optionLabel="title"
        optionValue="id"
        :placeholder="t('publication.common.category_placeholder')"
        class="w-full"
        :disabled="disabled"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-description" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('publication.editor.settings.description') }}
      </label>
      <Textarea
        id="panel-description"
        v-model="shortDescription"
        :placeholder="t('publication.editor.settings.description_placeholder')"
        rows="3"
        class="w-full"
        :disabled="disabled"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label for="panel-tags" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('publication.editor.settings.tags') }}
      </label>
      <AutoComplete
        id="panel-tags"
        v-model="tags"
        :suggestions="tagSuggestions"
        multiple
        fluid
        :disabled="disabled"
        :placeholder="t('publication.editor.settings.tags_placeholder')"
        @complete="handleTagSearch"
        @keydown.enter.prevent="handleTagEnter"
      />
      <small class="text-surface-500 text-xs">
        {{ t('publication.editor.settings.tags_help') }}
      </small>
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
        {{ t('publication.editor.settings.cover') }}
      </label>

      <div v-if="coverUrl" class="relative inline-block">
        <img
          :src="coverUrl"
          :alt="t('publication.editor.settings.cover')"
          class="w-full rounded-lg border border-surface-200 dark:border-surface-700"
        />
        <Button
          icon="pi pi-times"
          :aria-label="t('publication.editor.settings.remove_cover')"
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
          {{ t('publication.editor.settings.no_cover') }}
        </p>
      </div>

      <FileUpload
        ref="fileUploadRef"
        mode="basic"
        accept="image/*"
        :maxFileSize="4000000"
        :chooseLabel="coverUrl ? t('publication.editor.settings.replace_cover') : t('publication.editor.settings.choose_cover')"
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
        {{ t('publication.editor.settings.cover_limits') }}
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
import { useI18n } from 'vue-i18n'
import tagsApi from '../../api/publication/tags.js'
import { ENABLED_LOCALES } from '../../i18n/locale.js'
import { usePublicationEditStore } from '../../store/publication/publicationEdit.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { languageOptions, offersLanguageChoice } from '../../utils/publicationLanguage.js'

defineProps({
  disabled: { type: Boolean, default: false },
  wordCount: { type: Number, default: 0 },
  readingTime: { type: Number, default: 0 }
})

const shortDescription = defineModel('shortDescription', { type: String, default: '' })
const categoryId = defineModel('categoryId', { type: Number, default: null })
const tags = defineModel('tags', { type: Array, default: () => [] })
const language = defineModel('language', { type: String, default: null })

const { t } = useI18n()
const publicationEditStore = usePublicationEditStore()
const publicationsStore = usePublicationsStore()
const toast = useToast()

const tagSuggestions = ref([])
const fileUploadRef = ref(null)

const coverUrl = computed(() => publicationEditStore.publication?.cover_url || null)

const languageChoices = computed(() =>
  languageOptions(ENABLED_LOCALES, publicationEditStore.publication?.translation_languages)
)

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
      summary: t('publication.common.error'),
      detail: t('publication.editor.settings.cover_upload_failed'),
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
      summary: t('publication.common.error'),
      detail: t('publication.editor.settings.cover_remove_failed'),
      life: 3000
    })
  }
}
</script>
