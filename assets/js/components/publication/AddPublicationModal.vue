<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('publication.user.add_modal.header')"
    :style="{ width: '450px' }"
    :closable="!isLoading"
    :closeOnEscape="!isLoading"
    @hide="handleClose"
  >
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-2">
        <label for="category" class="font-medium text-surface-700 dark:text-surface-200">
          {{ t('publication.common.category') }}
        </label>
        <Select
          id="category"
          v-model="selectedCategory"
          :options="publicationsStore.publicationCategories"
          optionLabel="title"
          optionValue="id"
          :placeholder="t('publication.common.category_placeholder')"
          class="w-full"
          :disabled="isLoading"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="title" class="font-medium text-surface-700 dark:text-surface-200">
          {{ t('publication.common.title') }}
        </label>
        <InputText
          id="title"
          v-model="title"
          :placeholder="t('publication.user.add_modal.title_placeholder')"
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
          @click="handleClose"
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
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePublicationEditStore } from '../../store/publication/publicationEdit.js'
import { usePublicationsStore } from '../../store/publication/publications.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'created'])

const { t } = useI18n()
const router = useRouter()
const publicationsStore = usePublicationsStore()
const publicationEditStore = usePublicationEditStore()

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedCategory = ref(null)
const title = ref('')
const isLoading = ref(false)
const errors = ref([])

const canCreate = computed(() => {
  return selectedCategory.value && title.value.trim().length >= 3 && !isLoading.value
})

onMounted(async () => {
  if (publicationsStore.publicationCategories.length === 0) {
    await publicationsStore.loadCategories()
  }
})

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      selectedCategory.value = null
      title.value = ''
      errors.value = []
    }
  }
)

async function handleCreate() {
  if (!canCreate.value) return

  isLoading.value = true
  errors.value = []

  const result = await publicationEditStore.createPublication({
    title: title.value.trim(),
    categoryId: selectedCategory.value
  })

  if (result) {
    emit('created', result)
    visible.value = false
    router.push({ name: 'app_user_publication_edit', params: { id: result.id } })
  } else {
    errors.value = publicationEditStore.errors
  }

  isLoading.value = false
}

function handleClose() {
  if (!isLoading.value) {
    visible.value = false
  }
}
</script>
