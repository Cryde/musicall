<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
            Mes publications
          </h1>
          <p class="text-surface-500 dark:text-surface-400">
            <template v-if="userPublicationsStore.totalItems > 0">
              Vous avez posté {{ userPublicationsStore.totalItems }} publication(s)
            </template>
            <template v-else>
              Gérez vos publications
            </template>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            label="Poster une découverte"
            icon="pi pi-video"
            severity="info"
            @click="handleOpenDiscoverModal"
          />
          <Button
            label="Poster une publication"
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
          placeholder="Toutes les catégories"
          showClear
          class="w-full md:w-56"
          @change="handleFilterChange"
        />
        <Select
          v-model="selectedStatus"
          :options="statusOptions"
          optionLabel="label"
          optionValue="id"
          placeholder="Tous les statuts"
          showClear
          class="w-full md:w-48"
          @change="handleFilterChange"
        />
        <Button
          v-if="hasActiveFilters"
          label="Réinitialiser les filtres"
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
          <p class="text-lg font-medium">Vous n'avez pas encore de publications</p>
          <p class="text-sm mt-2">Cliquez sur "Poster une découverte" ou "Poster une publication" pour en créer une</p>
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
          <Column header="Image" style="width: 80px">
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

          <Column field="title" header="Titre" sortable style="min-width: 200px">
            <template #body="{ data }">
              <span class="font-medium">{{ data.title }}</span>
            </template>
          </Column>

          <Column field="category" header="Catégorie" style="min-width: 150px">
            <template #body="{ data }">
              <Tag :value="data.category?.title" severity="secondary" />
            </template>
          </Column>

          <Column field="creation_datetime" header="Création" sortable style="min-width: 120px">
            <template #body="{ data }">
              {{ formatDate(data.creation_datetime) }}
            </template>
          </Column>

          <Column field="edition_datetime" header="Édition" sortable style="min-width: 120px">
            <template #body="{ data }">
              <span v-if="data.edition_datetime">{{ formatDate(data.edition_datetime) }}</span>
              <span v-else class="text-surface-400">-</span>
            </template>
          </Column>

          <Column field="status_label" header="Statut" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="data.status_label" :severity="getStatusSeverity(data.status_id)" />
            </template>
          </Column>

          <Column header="Actions" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="'Soumettre la publication'"
                  icon="pi pi-send"
                  severity="success"
                  text
                  rounded
                  size="small"
                  @click="handleSubmit(data)"
                />
                <Button
                  v-tooltip.top="'Voir la publication'"
                  icon="pi pi-eye"
                  severity="info"
                  text
                  rounded
                  size="small"
                  @click="handleView(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="'Modifier la publication'"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleEdit(data)"
                />
                <Button
                  v-if="data.status_id === STATUS_DRAFT"
                  v-tooltip.top="'Supprimer la publication'"
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
            <span class="text-sm text-surface-500 dark:text-surface-400">Éléments par page :</span>
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
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AddDiscoverModal from '../../../components/Publication/AddDiscoverModal.vue'
import AddPublicationModal from '../../../components/publication/AddPublicationModal.vue'
import publicationApi from '../../../api/user/publication.js'
import { usePublicationsStore } from '../../../store/publication/publications.js'
import { useUserPublicationsStore } from '../../../store/publication/userPublications.js'
import { useVideoStore } from '../../../store/publication/video.js'

useTitle('Mes publications - MusicAll')

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

const statusOptions = [
  { label: 'Brouillon', id: STATUS_DRAFT },
  { label: 'Publié', id: STATUS_ONLINE },
  { label: 'En validation', id: STATUS_PENDING }
]

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
  return new Date(dateString).toLocaleDateString('fr-FR', {
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

function handleSubmit(publication) {
  confirm.require({
    message: `Soumettre la publication "${publication.title}" pour validation ?`,
    header: 'Confirmation',
    icon: 'pi pi-send',
    acceptLabel: 'Soumettre',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await publicationApi.submit(publication.id)
        await userPublicationsStore.loadPublications()
        toast.add({
          severity: 'success',
          summary: 'Succès',
          detail: 'Publication soumise pour validation',
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de soumettre la publication',
          life: 3000
        })
      }
    }
  })
}

function confirmDelete(publication) {
  confirm.require({
    message: 'Êtes-vous sûr de vouloir supprimer cette publication ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      const success = await userPublicationsStore.deletePublication(publication.id)
      if (success) {
        toast.add({
          severity: 'success',
          summary: 'Succès',
          detail: 'Publication supprimée avec succès',
          life: 3000
        })
      } else {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la publication',
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
