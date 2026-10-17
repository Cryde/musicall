<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
            {{ t('publication.user.heading') }}
          </h1>
          <p class="text-surface-500 dark:text-surface-400">
            <template v-if="userPublicationsStore.totalItems > 0">
              {{ t('publication.user.count', { count: userPublicationsStore.totalItems }) }}
            </template>
            <template v-else>
              {{ t('publication.user.subtitle') }}
            </template>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            :label="t('publication.common.add_discover')"
            icon="pi pi-video"
            severity="info"
            @click="handleOpenDiscoverModal"
          />
          <Button
            :label="t('publication.common.add_publication')"
            icon="pi pi-file-edit"
            severity="info"
            outlined
            @click="handleOpenPublicationModal"
          />
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-4">
        <Select
          v-model="selectedCategory"
          :options="publicationsStore.publicationCategories"
          optionLabel="title"
          optionValue="id"
          :placeholder="t('publication.user_list.all_categories')"
          showClear
          class="w-full md:w-56"
          @change="handleFilterChange"
        />
        <Select
          v-model="selectedStatus"
          :options="statusOptions"
          optionLabel="label"
          optionValue="id"
          :placeholder="t('publication.user_list.all_statuses')"
          showClear
          class="w-full md:w-48"
          @change="handleFilterChange"
        />
        <Button
          v-if="hasActiveFilters"
          :label="t('publication.user_list.reset_filters')"
          icon="pi pi-filter-slash"
          severity="secondary"
          text
          size="small"
          @click="handleResetFilters"
        />
      </div>

      <div v-if="userPublicationsStore.isLoading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div v-else-if="userPublicationsStore.publications.length === 0" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm">
        <div class="flex flex-col items-center justify-center py-12 text-surface-500 dark:text-surface-400">
          <i class="pi pi-file text-4xl mb-4" />
          <p class="text-lg font-medium">{{ t('publication.user.empty') }}</p>
          <p class="text-sm mt-2">{{ t('publication.user.empty_help') }}</p>
        </div>
      </div>

      <template v-else>
        <DataTable
          :value="userPublicationsStore.publications"
          :loading="userPublicationsStore.isLoading"
          stripedRows
          tableStyle="min-width: 50rem"
          :sortField="sortField"
          :sortOrder="sortOrder"
          @sort="handleSort"
        >
          <Column :header="t('publication.user_list.column_image')" style="width: 80px">
            <template #body="{ data }">
              <img
                v-if="data.cover_url"
                :src="data.cover_url"
                :alt="data.title"
                class="w-12 h-12 object-cover rounded"
              />
              <div v-else class="w-12 h-12 bg-surface-200 dark:bg-surface-700 rounded flex items-center justify-center">
                <i class="pi pi-image text-surface-400" />
              </div>
            </template>
          </Column>

          <Column field="title" :header="t('publication.common.title')" sortable style="min-width: 200px">
            <template #body="{ data }">
              <span class="font-medium">{{ data.title }}</span>
            </template>
          </Column>

          <Column
            v-if="offersLanguageChoice(ENABLED_LOCALES)"
            field="language"
            :header="t('publication.user_list.column_language')"
            style="min-width: 120px"
          >
            <template #body="{ data }">
              <PublicationLanguageLabel :language="data.language" />
            </template>
          </Column>

          <Column field="category" :header="t('publication.common.category')" style="min-width: 150px">
            <template #body="{ data }">
              <Tag :value="data.category?.title" severity="secondary" />
            </template>
          </Column>

          <Column field="creation_datetime" :header="t('publication.user_list.column_created')" sortable style="min-width: 120px">
            <template #body="{ data }">
              {{ formatDate(data.creation_datetime) }}
            </template>
          </Column>

          <Column field="edition_datetime" :header="t('publication.user_list.column_edited')" sortable style="min-width: 120px">
            <template #body="{ data }">
              <span v-if="data.edition_datetime">{{ formatDate(data.edition_datetime) }}</span>
              <span v-else class="text-surface-400">-</span>
            </template>
          </Column>

          <Column field="status_id" :header="t('publication.user_list.column_status')" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="publicationStatusLabel(data.status_id)" :severity="getStatusSeverity(data.status_id)" />
            </template>
          </Column>

          <Column :header="t('publication.user_list.column_actions')" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.user.submit')"
                  :aria-label="t('publication.user.submit')"
                  icon="pi pi-send"
                  severity="success"
                  text
                  rounded
                  size="small"
                  @click="handleSubmit(data)"
                />
                <Button
                  v-tooltip.top="t('publication.user.view')"
                  :aria-label="t('publication.user.view')"
                  icon="pi pi-eye"
                  severity="info"
                  text
                  rounded
                  size="small"
                  @click="handleView(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.user.edit')"
                  :aria-label="t('publication.user.edit')"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleEdit(data)"
                />
                <Button
                  v-if="translationLanguagesLeft(data, ENABLED_LOCALES).length > 0"
                  v-tooltip.top="t('publication.user.translate')"
                  :aria-label="t('publication.user.translate')"
                  icon="pi pi-language"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleTranslate(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.user.delete')"
                  :aria-label="t('publication.user.delete')"
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

        <div v-if="userPublicationsStore.totalItems > 0" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-4">
          <Paginator
            :rows="userPublicationsStore.itemsPerPage"
            :totalRecords="userPublicationsStore.totalItems"
            :first="(userPublicationsStore.currentPage - 1) * userPublicationsStore.itemsPerPage"
            @page="handlePageChange"
          />
          <div class="flex items-center gap-2">
            <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('publication.user_list.items_per_page') }}</span>
            <Select
              :modelValue="userPublicationsStore.itemsPerPage"
              :options="itemsPerPageOptions"
              optionLabel="label"
              optionValue="value"
              class="w-24"
              @update:modelValue="handleItemsPerPageChange"
            />
          </div>
        </div>
      </template>
    </div>

    <AddDiscoverModal @published="handleDiscoverPublished" />
    <AddPublicationModal v-model="showAddPublicationModal" @created="handlePublicationCreated" />
    <AddTranslationModal v-model:visible="showAddTranslationModal" :publication="publicationToTranslate" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import publicationApi from '../../../api/user/publication.js'
import AddDiscoverModal from '../../../components/Publication/AddDiscoverModal.vue'
import AddTranslationModal from '../../../components/Publication/AddTranslationModal.vue'
import PublicationLanguageLabel from '../../../components/Publication/PublicationLanguageLabel.vue'
import AddPublicationModal from '../../../components/publication/AddPublicationModal.vue'
import { publicationStatusLabel } from '../../../constants/publicationStatus.js'
import { intlLocale } from '../../../i18n/formatLocale.js'
import { ENABLED_LOCALES } from '../../../i18n/locale.js'
import { usePublicationsStore } from '../../../store/publication/publications.js'
import { useUserPublicationsStore } from '../../../store/publication/userPublications.js'
import { useVideoStore } from '../../../store/publication/video.js'
import {
  offersLanguageChoice,
  translationLanguagesLeft
} from '../../../utils/publicationLanguage.js'

const { t } = useI18n()

useTitle(() => t('publication.user.page_title'))

const STATUS_DRAFT = 0
const STATUS_ONLINE = 1
const STATUS_PENDING = 2

const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userPublicationsStore = useUserPublicationsStore()
const publicationsStore = usePublicationsStore()
const videoStore = useVideoStore()

const selectedCategory = ref(null)
const selectedStatus = ref(null)
const sortField = ref('creation_datetime')
const sortOrder = ref(-1)
const showAddPublicationModal = ref(false)
const showAddTranslationModal = ref(false)
const publicationToTranslate = ref(null)

const statusOptions = computed(() => [
  { label: t('publication.user_list.status.draft'), id: STATUS_DRAFT },
  { label: t('publication.user_list.status.online'), id: STATUS_ONLINE },
  { label: t('publication.user_list.status.pending'), id: STATUS_PENDING }
])

const itemsPerPageOptions = [
  { label: '10', value: 10 },
  { label: '20', value: 20 },
  { label: '50', value: 50 },
  { label: '100', value: 100 }
]

const hasActiveFilters = computed(() => {
  return selectedCategory.value !== null || selectedStatus.value !== null
})

onMounted(async () => {
  await publicationsStore.loadCategories()
  await userPublicationsStore.loadPublications()
})

onUnmounted(() => {
  userPublicationsStore.clear()
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString(intlLocale(), {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function getStatusSeverity(statusId) {
  switch (statusId) {
    case STATUS_ONLINE:
      return 'success'
    case STATUS_PENDING:
      return 'warn'
    default:
      return 'secondary'
  }
}

async function handleFilterChange() {
  userPublicationsStore.setFilters({
    category: selectedCategory.value,
    status: selectedStatus.value
  })
  await userPublicationsStore.loadPublications()
}

async function handleResetFilters() {
  selectedCategory.value = null
  selectedStatus.value = null
  userPublicationsStore.resetFilters()
  await userPublicationsStore.loadPublications()
}

async function handleSort(event) {
  const fieldMap = {
    title: 'title',
    creation_datetime: 'creation_datetime',
    edition_datetime: 'edition_datetime'
  }

  sortField.value = event.sortField
  sortOrder.value = event.sortOrder

  userPublicationsStore.setFilters({
    sortBy: fieldMap[event.sortField] || 'creation_datetime',
    sortOrder: event.sortOrder === 1 ? 'asc' : 'desc'
  })
  await userPublicationsStore.loadPublications()
}

async function handlePageChange(event) {
  userPublicationsStore.setPage(event.page + 1)
  await userPublicationsStore.loadPublications()
}

async function handleItemsPerPageChange(value) {
  userPublicationsStore.setItemsPerPage(value)
  await userPublicationsStore.loadPublications()
}

function handleView(publication) {
  // Redirect to preview for draft/pending, to public page for online
  if (publication.status_id === STATUS_ONLINE) {
    router.push({ name: 'app_publication_show', params: { slug: publication.slug } })
  } else {
    router.push({ name: 'app_user_publication_preview', params: { id: publication.id } })
  }
}

function handleEdit(publication) {
  router.push({ name: 'app_user_publication_edit', params: { id: publication.id } })
}

function handleTranslate(publication) {
  publicationToTranslate.value = publication
  showAddTranslationModal.value = true
}

function handleSubmit(publication) {
  confirm.require({
    message: t('publication.user.submit_confirm', { title: publication.title }),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-send',
    acceptLabel: t('publication.common.submit'),
    rejectLabel: t('publication.common.cancel'),
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await publicationApi.submit(publication.id)
        await userPublicationsStore.loadPublications()
        toast.add({
          severity: 'success',
          summary: t('publication.common.success'),
          detail: t('publication.user.submitted'),
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.editor.submit_failed'),
          life: 3000
        })
      }
    }
  })
}

function confirmDelete(publication) {
  confirm.require({
    message: t('publication.user.delete_confirm'),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('publication.common.cancel'),
    acceptLabel: t('publication.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      const success = await userPublicationsStore.deletePublication(publication.id)
      if (success) {
        toast.add({
          severity: 'success',
          summary: t('publication.common.success'),
          detail: t('publication.user.deleted'),
          life: 3000
        })
      } else {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.user.delete_failed'),
          life: 3000
        })
      }
    }
  })
}

function handleOpenDiscoverModal() {
  videoStore.openModal()
}

function handleOpenPublicationModal() {
  showAddPublicationModal.value = true
}

async function handleDiscoverPublished() {
  await userPublicationsStore.loadPublications()
}

async function handlePublicationCreated() {
  await userPublicationsStore.loadPublications()
}
</script>
