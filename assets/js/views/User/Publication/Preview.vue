<template>
  <div class="py-6 md:py-10">
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="error" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-exclamation-triangle text-4xl text-red-500 mb-4" />
      <p class="text-lg text-surface-700 dark:text-surface-300">{{ error }}</p>
      <Button
        label="Retour à mes publications"
        icon="pi pi-arrow-left"
        class="mt-4"
        @click="router.push({ name: 'app_user_publications' })"
      />
    </div>

    <template v-else-if="publication">
      <!-- Preview Banner -->
      <Message severity="warn" :closable="false" class="mb-6">
        <div class="flex items-center justify-between w-full gap-4">
          <div class="flex items-center gap-2">
            <i class="pi pi-eye" />
            <span>
              <strong>Mode prévisualisation</strong> -
              Cette publication est en
              <Tag :value="publication.status_label" :severity="getStatusSeverity(publication.status_id)" class="mx-1" />
            </span>
          </div>
          <div class="flex gap-2">
            <Button
              v-if="publication.status_id === STATUS_DRAFT"
              label="Modifier"
              icon="pi pi-pencil"
              size="small"
              severity="secondary"
              @click="router.push({ name: 'app_user_publication_edit', params: { id: publication.id } })"
            />
            <Button
              label="Mes publications"
              icon="pi pi-list"
              size="small"
              severity="secondary"
              outlined
              @click="router.push({ name: 'app_user_publications' })"
            />
          </div>
        </div>
      </Message>

      <!-- Breadcrumb -->
      <div class="flex justify-end mb-4">
        <Breadcrumb :items="breadCrumbs" />
      </div>

      <!-- Publication Content -->
      <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ publication.title }}
          </h1>
          <div class="text-sm leading-tight text-surface-500 dark:text-surface-300 mt-5">
            Par <strong>{{ publication.author?.username }}</strong>
          </div>
        </div>
      </div>

      <div
        class="box content is-shadowless publication-container p-3 bg-surface-0 dark:bg-surface-800 rounded-md mt-6"
        v-html="publication.content"
      />

      <!-- No comments in preview mode -->
      <div class="mt-8 p-6 bg-surface-100 dark:bg-surface-800 rounded-lg text-center">
        <i class="pi pi-comments text-3xl text-surface-400 mb-2" />
        <p class="text-surface-500 dark:text-surface-400">
          Les commentaires seront disponibles une fois la publication en ligne.
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import publicationApi from '../../../api/user/publication.js'
import Breadcrumb from '../../Global/Breadcrumb.vue'

const STATUS_DRAFT = 0
const STATUS_PENDING = 2

const route = useRoute()
const router = useRouter()

const publication = ref(null)
const isLoading = ref(true)
const error = ref(null)

useTitle(() => publication.value ? `Prévisualisation: ${publication.value.title} - MusicAll` : 'Prévisualisation - MusicAll')

onMounted(async () => {
  await loadPreview()
})

async function loadPreview() {
  isLoading.value = true
  error.value = null
  try {
    publication.value = await publicationApi.getPreview(route.params.id)
  } catch (e) {
    console.error('Failed to load preview:', e)
    if (e.response?.status === 403) {
      error.value = 'Vous n\'avez pas accès à cette publication'
    } else if (e.response?.status === 404) {
      error.value = 'Publication non trouvée'
    } else {
      error.value = 'Une erreur est survenue lors du chargement de la prévisualisation'
    }
  } finally {
    isLoading.value = false
  }
}

function getStatusSeverity(statusId) {
  switch (statusId) {
    case STATUS_PENDING:
      return 'warn'
    default:
      return 'secondary'
  }
}

const breadCrumbs = computed(() => {
  if (!publication.value) return []
  return [
    { label: 'Publications', to: { name: 'app_publications' } },
    { label: publication.value.category?.title || 'Sans catégorie' },
    { label: publication.value.title }
  ]
})
</script>
