<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('publication.gallery.user.heading') }}</h1>
          <p class="text-surface-500 dark:text-surface-400">
            <template v-if="userGalleriesStore.galleries.length > 0">
              {{ t('publication.gallery.user.count', { count: userGalleriesStore.galleries.length }) }}
            </template>
            <template v-else> {{ t('publication.gallery.user.subtitle') }} </template>
          </p>
        </div>
        <Button :label="t('publication.gallery.user.add')" icon="pi pi-images" severity="info" @click="openAddModal" />
      </div>

      <div v-if="userGalleriesStore.isLoading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div
        v-else-if="userGalleriesStore.galleries.length === 0"
        class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm"
      >
        <div class="flex flex-col items-center justify-center py-12 text-surface-500 dark:text-surface-400">
          <i class="pi pi-images text-4xl mb-4" />
          <p class="text-lg font-medium">{{ t('publication.gallery.user.empty') }}</p>
          <p class="text-sm mt-2">{{ t('publication.gallery.user.empty_help') }}</p>
        </div>
      </div>

      <template v-else>
        <DataTable :value="userGalleriesStore.galleries" stripedRows tableStyle="min-width: 50rem">
          <Column :header="t('publication.gallery.user.column_cover')" style="width: 80px">
            <template #body="{ data }">
              <img
                v-if="data.cover_image_url"
                :src="data.cover_image_url"
                :alt="data.title"
                class="w-12 h-12 object-cover rounded"
              />
              <div
                v-else
                class="w-12 h-12 bg-surface-200 dark:bg-surface-700 rounded flex items-center justify-center"
              >
                <i class="pi pi-image text-surface-400" />
              </div>
            </template>
          </Column>

          <Column field="title" :header="t('publication.common.title')" style="min-width: 200px">
            <template #body="{ data }">
              <span class="font-medium">{{ data.title }}</span>
            </template>
          </Column>

          <Column field="image_count" :header="t('publication.gallery.photos')" style="min-width: 100px">
            <template #body="{ data }">
              {{ t('publication.gallery.user.photo_count', { count: data.image_count }) }}
            </template>
          </Column>

          <Column field="status" :header="t('publication.user_list.column_status')" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="galleryStatusLabel(data.status)" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>

          <Column :header="t('publication.user_list.column_actions')" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="t('publication.gallery.user.submit')"
                  :aria-label="t('publication.gallery.user.submit')"
                  icon="pi pi-send"
                  severity="success"
                  text
                  rounded
                  size="small"
                  @click="handleSubmit(data)"
                />
                <Button
                  v-if="data.slug"
                  v-tooltip.top="t('publication.gallery.user.view')"
                  :aria-label="t('publication.gallery.user.view')"
                  icon="pi pi-eye"
                  severity="info"
                  text
                  rounded
                  size="small"
                  @click="handleView(data)"
                />
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="t('publication.gallery.user.edit')"
                  :aria-label="t('publication.gallery.user.edit')"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleEdit(data)"
                />
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="t('publication.gallery.user.delete')"
                  :aria-label="t('publication.gallery.user.delete')"
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </div>

    <!-- Add Gallery Modal -->
    <Dialog
      v-model:visible="showAddModal"
      modal
      :header="t('publication.gallery.user.new_header')"
      :style="{ width: '25rem' }"
    >
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="title" class="font-medium">{{ t('publication.gallery.user.title_label') }}</label>
          <InputText
            id="title"
            v-model="newGalleryTitle"
            :placeholder="t('publication.gallery.user.title_placeholder')"
            :invalid="submitted && !newGalleryTitle.trim()"
          />
        </div>
      </div>
      <template #footer>
        <Button :label="t('publication.common.cancel')" severity="secondary" text @click="closeAddModal" />
        <Button :label="t('publication.common.create')" icon="pi pi-check" :loading="isCreating" @click="createGallery" />
      </template>
    </Dialog>

  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { galleryStatusLabel } from '../../../constants/publicationStatus.js'
import { useUserGalleriesStore } from '../../../store/gallery/userGalleries.js'

const { t } = useI18n()

useTitle(() => t('publication.gallery.user.page_title'))

const STATUS_DRAFT = 1
const STATUS_ONLINE = 0
const STATUS_PENDING = 2

const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userGalleriesStore = useUserGalleriesStore()

const showAddModal = ref(false)
const newGalleryTitle = ref('')
const isCreating = ref(false)
const submitted = ref(false)

onMounted(async () => {
  await userGalleriesStore.loadGalleries()
})

onUnmounted(() => {
  userGalleriesStore.clear()
})

function getStatusSeverity(status) {
  switch (status) {
    case STATUS_ONLINE:
      return 'success'
    case STATUS_PENDING:
      return 'warn'
    default:
      return 'secondary'
  }
}

function openAddModal() {
  showAddModal.value = true
  newGalleryTitle.value = ''
  submitted.value = false
}

function closeAddModal() {
  showAddModal.value = false
  newGalleryTitle.value = ''
  submitted.value = false
}

async function createGallery() {
  submitted.value = true
  if (!newGalleryTitle.value.trim()) return

  isCreating.value = true
  try {
    const gallery = await userGalleriesStore.createGallery(newGalleryTitle.value)
    trackUmamiEvent('gallery-create')
    closeAddModal()
    toast.add({
      severity: 'success',
      summary: t('publication.common.success'),
      detail: t('publication.gallery.user.created'),
      life: 3000
    })
    // Redirect to edit page
    router.push({ name: 'app_user_gallery_edit', params: { id: gallery.id } })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('publication.common.error'),
      detail: t('publication.gallery.user.create_failed'),
      life: 3000
    })
  } finally {
    isCreating.value = false
  }
}

function handleView(gallery) {
  if (gallery.status === STATUS_ONLINE) {
    router.push({ name: 'app_gallery_show', params: { slug: gallery.slug } })
  } else {
    router.push({ name: 'app_user_gallery_preview', params: { id: gallery.id } })
  }
}

function handleEdit(gallery) {
  router.push({ name: 'app_user_gallery_edit', params: { id: gallery.id } })
}

async function handleSubmit(gallery) {
  confirm.require({
    message: t('publication.gallery.user.submit_confirm'),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('publication.common.cancel'),
    acceptLabel: t('publication.common.submit'),
    accept: async () => {
      try {
        await userGalleriesStore.submitGallery(gallery.id)
        toast.add({
          severity: 'success',
          summary: t('publication.common.success'),
          detail: t('publication.gallery.user.submitted'),
          life: 3000
        })
      } catch (e) {
        const message = e.response?.data?.detail || t('publication.gallery.user.submit_failed')
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: message,
          life: 5000
        })
      }
    }
  })
}

function confirmDelete(gallery) {
  confirm.require({
    message: t('publication.gallery.user.delete_confirm'),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('publication.common.cancel'),
    acceptLabel: t('publication.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await userGalleriesStore.deleteGallery(gallery.id)
        toast.add({
          severity: 'success',
          summary: t('publication.common.success'),
          detail: t('publication.gallery.user.deleted'),
          life: 3000
        })
      } catch {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.gallery.user.delete_failed'),
          life: 3000
        })
      }
    }
  })
}
</script>
