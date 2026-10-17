<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('publication.user.translation_modal.header')"
    :style="{ width: '450px' }"
    :closable="!isLoading"
    :closeOnEscape="!isLoading"
  >
    <div v-if="publication" class="flex flex-col gap-4">
      <p class="text-sm text-surface-600 dark:text-surface-300">
        {{ t('publication.user.translation_modal.intro', { title: publication.title }) }}
      </p>

      <div class="flex flex-col gap-2">
        <label for="translation-language" class="font-medium text-surface-700 dark:text-surface-200">
          {{ t('publication.user.translation_modal.language') }}
        </label>
        <Select
          id="translation-language"
          v-model="language"
          :options="languageChoices"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('publication.user.translation_modal.language_placeholder')"
          class="w-full"
          :disabled="isLoading"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="translation-title" class="font-medium text-surface-700 dark:text-surface-200">
          {{ t('publication.common.title') }}
        </label>
        <InputText
          id="translation-title"
          v-model="title"
          :placeholder="t('publication.user.translation_modal.title_placeholder')"
          class="w-full"
          :disabled="isLoading"
          @keyup.enter="handleCreate"
        />
      </div>

      <Message v-if="errors.length > 0" severity="error" :closable="false">
        <ul class="list-disc list-inside">
          <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
        </ul>
      </Message>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="t('publication.common.cancel')"
          severity="secondary"
          text
          :disabled="isLoading"
          @click="visible = false"
        />
        <Button
          :label="t('publication.common.create')"
          icon="pi pi-check"
          :loading="isLoading"
          :disabled="!canCreate"
          @click="handleCreate"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ENABLED_LOCALES } from '../../i18n/locale.js'
import { usePublicationEditStore } from '../../store/publication/publicationEdit.js'
import { languageOptions, translationLanguagesLeft } from '../../utils/publicationLanguage.js'

// The row of the author's list being translated: its id, title, language and translation_languages.
const props = defineProps({
  publication: { type: Object, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const router = useRouter()
const publicationEditStore = usePublicationEditStore()

const MIN_TITLE_LENGTH = 3

const language = ref(null)
const title = ref('')
const isLoading = ref(false)
const errors = ref([])

const languageChoices = computed(() =>
  props.publication
    ? languageOptions(translationLanguagesLeft(props.publication, ENABLED_LOCALES))
    : []
)

const canCreate = computed(
  () => language.value && title.value.trim().length >= MIN_TITLE_LENGTH && !isLoading.value
)

watch(visible, (isVisible) => {
  if (isVisible) {
    // A single language left is the usual case, so it comes chosen.
    language.value = languageChoices.value.length === 1 ? languageChoices.value[0].value : null
    title.value = ''
    errors.value = []
  }
})

async function handleCreate() {
  if (!canCreate.value) return

  isLoading.value = true
  errors.value = []

  const result = await publicationEditStore.createTranslation(props.publication.id, {
    title: title.value.trim(),
    language: language.value
  })

  isLoading.value = false
  if (result) {
    visible.value = false
    router.push({ name: 'app_user_publication_edit', params: { id: result.id } })
  } else {
    errors.value = publicationEditStore.errors
  }
}
</script>
