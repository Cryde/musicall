<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
            {{ t('publication.course.user.heading') }}
          </h1>
          <p class="text-surface-500 dark:text-surface-400">
            <template v-if="userCoursesStore.totalItems > 0">
              {{ t('publication.course.user.count', { count: userCoursesStore.totalItems }) }}
            </template>
            <template v-else>
              {{ t('publication.course.user.subtitle') }}
            </template>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            :label="t('publication.course.list.add')"
            icon="pi pi-book"
            severity="info"
            @click="handleOpenCourseModal"
          />
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-4">
        <Select
          v-model="selectedCategory"
          :options="coursesStore.courseCategories"
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

      <div v-if="userCoursesStore.isLoading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div v-else-if="userCoursesStore.courses.length === 0" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm">
        <div class="flex flex-col items-center justify-center py-12 text-surface-500 dark:text-surface-400">
          <i class="pi pi-book text-4xl mb-4" />
          <p class="text-lg font-medium">{{ t('publication.course.user.empty') }}</p>
          <p class="text-sm mt-2">{{ t('publication.course.user.empty_help') }}</p>
        </div>
      </div>

      <template v-else>
        <DataTable
          :value="userCoursesStore.courses"
          :loading="userCoursesStore.isLoading"
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

          <Column field="status_label" :header="t('publication.user_list.column_status')" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="data.status_label" :severity="getStatusSeverity(data.status_id)" />
            </template>
          </Column>

          <Column :header="t('publication.user_list.column_actions')" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.course.user.submit')"
                  :aria-label="t('publication.course.user.submit')"
                  icon="pi pi-send"
                  severity="success"
                  text
                  rounded
                  size="small"
                  @click="handleSubmit(data)"
                />
                <Button
                  v-tooltip.top="t('publication.course.user.view')"
                  :aria-label="t('publication.course.user.view')"
                  icon="pi pi-eye"
                  severity="info"
                  text
                  rounded
                  size="small"
                  :disabled="data.status_id === STATUS_DRAFT"
                  @click="handleView(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.course.user.edit')"
                  :aria-label="t('publication.course.user.edit')"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleEdit(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="t('publication.course.user.delete')"
                  :aria-label="t('publication.course.user.delete')"
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

        <div v-if="userCoursesStore.totalItems > 0" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-4">
          <Paginator
            :rows="userCoursesStore.itemsPerPage"
            :totalRecords="userCoursesStore.totalItems"
            :first="(userCoursesStore.currentPage - 1) * userCoursesStore.itemsPerPage"
            @page="handlePageChange"
          />
          <div class="flex items-center gap-2">
            <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('publication.user_list.items_per_page') }}</span>
            <Select
              :modelValue="userCoursesStore.itemsPerPage"
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
import { intlLocale } from '../../../i18n/formatLocale.js'
import { useCoursesStore } from '../../../store/course/course.js'
import { useUserCoursesStore } from '../../../store/course/userCourses.js'

const { t } = useI18n()

useTitle(() => t('publication.course.user.page_title'))

const STATUS_DRAFT = 0
const STATUS_ONLINE = 1
const STATUS_PENDING = 2

const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userCoursesStore = useUserCoursesStore()
const coursesStore = useCoursesStore()

const selectedCategory = ref(null)
const selectedStatus = ref(null)
const sortField = ref('creation_datetime')
const sortOrder = ref(-1)

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
  await coursesStore.loadCategories()
  await userCoursesStore.loadCourses()
})

onUnmounted(() => {
  userCoursesStore.clear()
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
  userCoursesStore.setFilters({
    category: selectedCategory.value,
    status: selectedStatus.value
  })
  await userCoursesStore.loadCourses()
}

async function handleResetFilters() {
  selectedCategory.value = null
  selectedStatus.value = null
  userCoursesStore.resetFilters()
  await userCoursesStore.loadCourses()
}

async function handleSort(event) {
  const fieldMap = {
    title: 'title',
    creation_datetime: 'creation_datetime',
    edition_datetime: 'edition_datetime'
  }

  sortField.value = event.sortField
  sortOrder.value = event.sortOrder

  userCoursesStore.setFilters({
    sortBy: fieldMap[event.sortField] || 'creation_datetime',
    sortOrder: event.sortOrder === 1 ? 'asc' : 'desc'
  })
  await userCoursesStore.loadCourses()
}

async function handlePageChange(event) {
  userCoursesStore.setPage(event.page + 1)
  await userCoursesStore.loadCourses()
}

async function handleItemsPerPageChange(value) {
  userCoursesStore.setItemsPerPage(value)
  await userCoursesStore.loadCourses()
}

function handleView(course) {
  router.push({ name: 'app_course_show', params: { slug: course.slug } })
}

function handleEdit(_course) {
  // TODO: Implement edit page
  toast.add({
    severity: 'info',
    summary: t('publication.common.information'),
    detail: t('publication.course.user.edit_coming_soon'),
    life: 3000
  })
}

function handleSubmit(_course) {
  // TODO: Implement submit functionality
  toast.add({
    severity: 'info',
    summary: t('publication.common.information'),
    detail: t('publication.course.user.submit_coming_soon'),
    life: 3000
  })
}

function confirmDelete(course) {
  confirm.require({
    message: t('publication.course.user.delete_confirm'),
    header: t('publication.common.confirmation'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('publication.common.cancel'),
    acceptLabel: t('publication.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      const success = await userCoursesStore.deleteCourse(course.id)
      if (success) {
        toast.add({
          severity: 'success',
          summary: t('publication.common.success'),
          detail: t('publication.course.user.deleted'),
          life: 3000
        })
      } else {
        toast.add({
          severity: 'error',
          summary: t('publication.common.error'),
          detail: t('publication.course.user.delete_failed'),
          life: 3000
        })
      }
    }
  })
}

function handleOpenCourseModal() {
  // TODO: Implement course creation modal
  toast.add({
    severity: 'info',
    summary: t('publication.common.information'),
    detail: t('publication.course.user.create_coming_soon'),
    life: 3000
  })
}
</script>
