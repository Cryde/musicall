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
      <Message :severity="isAdminReview ? 'info' : 'warn'" :closable="false" class="mb-6">
        <div class="flex items-center justify-between w-full gap-4 flex-wrap">
          <div class="flex items-center gap-2">
            <i :class="isAdminReview ? 'pi pi-shield' : 'pi pi-eye'" />
            <span>
              <strong>{{ isAdminReview ? 'Validation admin' : 'Mode prévisualisation' }}</strong> -
              Cette publication est en
              <Tag :value="publication.status_label" :severity="getStatusSeverity(publication.status_id)" class="mx-1" />
            </span>
          </div>
          <div class="flex gap-2">
            <!-- Admin review buttons -->
            <template v-if="isAdminReview">
              <Button
                label="Valider"
                icon="pi pi-check"
                size="small"
                severity="success"
                :loading="isProcessing"
                @click="confirmApprove"
              />
              <Button
                label="Rejeter"
                icon="pi pi-times"
                size="small"
                severity="danger"
                :loading="isProcessing"
                @click="confirmReject"
              />
              <Button
                label="Retour"
                icon="pi pi-arrow-left"
                size="small"
                severity="secondary"
                outlined
                @click="router.push({ name: 'admin_publications_pending' })"
              />
            </template>
            <!-- Regular user buttons -->
            <template v-else>
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
            </template>
          </div>
        </div>
      </Message>

      <ConfirmDialog />

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
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import publicationApi from '../../../api/user/publication.js'
import adminPublicationApi from '../../../api/admin/publication.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import Breadcrumb from '../../Global/Breadcrumb.vue'

const STATUS_DRAFT = 0
const STATUS_PENDING = 2

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

const publication = ref(null)
const isLoading = ref(true)
const error = ref(null)
const isProcessing = ref(false)

const isAdminReview = computed(() => {
  return userSecurityStore.isAdmin && publication.value?.status_id === STATUS_PENDING
})

useTitle(() =>
  publication.value
    ? `Prévisualisation: ${publication.value.title} - MusicAll`
    : 'Prévisualisation - MusicAll'
)

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
      error.value = "Vous n'avez pas accès à cette publication"
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

function confirmApprove() {
  confirm.require({
    message: `Valider la publication "${publication.value.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-check-circle',
    acceptLabel: 'Valider',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-success',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminPublicationApi.approvePublication(publication.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'success',
          summary: 'Publication validée',
          detail: `La publication "${publication.value.title}" a été validée.`,
          life: 3000
        })
        router.push({ name: 'admin_publications_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors de la validation.',
          life: 3000
        })
      } finally {
        isProcessing.value = false
      }
    }
  })
}

function confirmReject() {
  confirm.require({
    message: `Rejeter la publication "${publication.value.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Rejeter',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminPublicationApi.rejectPublication(publication.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'info',
          summary: 'Publication rejetée',
          detail: `La publication "${publication.value.title}" a été rejetée.`,
          life: 3000
        })
        router.push({ name: 'admin_publications_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors du rejet.',
          life: 3000
        })
      } finally {
        isProcessing.value = false
      }
    }
  })
}
</script>
