<template>
  <div class="py-6 md:py-10">
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="error" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-exclamation-triangle text-4xl text-red-500 mb-4" />
      <p class="text-lg text-surface-700 dark:text-surface-300">{{ error }}</p>
      <Button
        :label="t('publication.preview.back')"
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
            <i18n-t keypath="publication.preview.banner" tag="span">
              <template #mode>
                <strong>{{ isAdminReview ? t('publication.review.admin_review') : t('publication.review.preview_mode') }}</strong>
              </template>
              <template #status>
                <Tag :value="publication.status_label" :severity="getStatusSeverity(publication.status_id)" class="mx-1" />
              </template>
            </i18n-t>
          </div>
          <div class="flex gap-2">
            <!-- Admin review buttons -->
            <template v-if="isAdminReview">
              <Button
                :label="t('publication.review.approve')"
                icon="pi pi-check"
                size="small"
                severity="success"
                :loading="isProcessing"
                @click="confirmApprove"
              />
              <Button
                :label="t('publication.review.reject')"
                icon="pi pi-times"
                size="small"
                severity="danger"
                :loading="isProcessing"
                @click="confirmReject"
              />
              <Button
                :label="t('publication.common.back')"
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
                :label="t('publication.common.edit')"
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                @click="router.push({ name: 'app_user_publication_edit', params: { id: publication.id } })"
              />
              <Button
                :label="t('publication.user.heading')"
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
          <i18n-t keypath="publication.preview.author" tag="div" class="text-sm leading-tight text-surface-500 dark:text-surface-300 mt-5">
            <template #author><strong>{{ previewAuthorName }}</strong></template>
          </i18n-t>
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
          {{ t('publication.preview.comments_unavailable') }}
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
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import adminPublicationApi from '../../../api/admin/publication.js'
import publicationApi from '../../../api/user/publication.js'
import { displayName } from '../../../helper/user/displayName.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import Breadcrumb from '../../Global/Breadcrumb.vue'

const STATUS_DRAFT = 0
const STATUS_PENDING = 2

const { t } = useI18n()
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

const previewAuthorName = computed(() =>
  publication.value?.author ? displayName(publication.value.author) : ''
)

const isAdminReview = computed(() => {
  return userSecurityStore.isAdmin && publication.value?.status_id === STATUS_PENDING
})

useTitle(() =>
  publication.value
    ? t('publication.review.page_title', { title: publication.value.title })
    : t('publication.review.page_title_empty')
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
      error.value = t('publication.preview.forbidden')
    } else if (e.response?.status === 404) {
      error.value = t('publication.preview.not_found')
    } else {
      error.value = t('publication.review.load_failed')
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
    { label: t('publication.common.publications'), to: { name: 'app_publications' } },
    { label: publication.value.category?.title || t('publication.preview.no_category') },
    { label: publication.value.title }
  ]
})

function confirmApprove() {
  confirm.require({
    message: t('publication.preview.approve_confirm', { title: publication.value.title }),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-check-circle',
    acceptLabel: t('publication.review.approve'),
    rejectLabel: t('publication.common.cancel'),
    acceptClass: 'p-button-success',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminPublicationApi.approvePublication(publication.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'success',
          summary: t('publication.preview.approved'),
          detail: t('publication.preview.approved_detail', { title: publication.value.title }),
          life: 3000
        })
        router.push({ name: 'admin_publications_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.review.approve_failed'),
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
    message: t('publication.preview.reject_confirm', { title: publication.value.title }),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('publication.review.reject'),
    rejectLabel: t('publication.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminPublicationApi.rejectPublication(publication.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'info',
          summary: t('publication.preview.rejected'),
          detail: t('publication.preview.rejected_detail', { title: publication.value.title }),
          life: 3000
        })
        router.push({ name: 'admin_publications_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.review.reject_failed'),
          life: 3000
        })
      } finally {
        isProcessing.value = false
      }
    }
  })
}
</script>
