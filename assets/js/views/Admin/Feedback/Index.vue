<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Retours utilisateurs</h1>
      <Tag v-if="totalItems > 0" :value="`${totalItems} retour(s)`" severity="secondary" />
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
      <Select
        v-model="statusFilter"
        :options="STATUS_FILTER_OPTIONS"
        option-label="label"
        option-value="value"
        aria-label="Filtrer par statut"
        class="w-48"
      />
      <Select
        v-model="typeFilter"
        :options="TYPE_FILTER_OPTIONS"
        option-label="label"
        option-value="value"
        aria-label="Filtrer par type"
        class="w-48"
      />
      <Select
        v-model="moduleFilter"
        :options="MODULE_FILTER_OPTIONS"
        option-label="label"
        option-value="value"
        aria-label="Filtrer par section"
        class="w-56"
      />
    </div>

    <div v-if="isLoading && !feedbacks.length" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-16 bg-surface-100 dark:bg-surface-800 animate-pulse rounded" />
    </div>

    <DataTable v-else :value="feedbacks" stripedRows class="text-sm">
      <Column field="creation_datetime" header="Reçu le" :style="{ width: '10rem' }">
        <template #body="{ data }">{{ formatDate(data.creation_datetime) }}</template>
      </Column>
      <Column field="type_label" header="Type" :style="{ width: '8rem' }">
        <template #body="{ data }">
          <Tag :value="data.type_label" :severity="TYPE_SEVERITY[data.type] ?? 'secondary'" />
        </template>
      </Column>
      <Column field="module_label" header="Section" :style="{ width: '12rem' }" />
      <Column field="message" header="Message">
        <template #body="{ data }">
          <p class="whitespace-pre-line">{{ data.message }}</p>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
            <span>{{ data.username ?? 'Visiteur anonyme' }}</span>
            <span v-if="data.email"> &middot; {{ data.email }}</span>
            <span v-if="data.band_space_name"> &middot; {{ data.band_space_name }}</span>
            <span> &middot; </span>
            <RouterLink :to="data.page_url" class="underline">{{ data.page_url }}</RouterLink>
          </p>
        </template>
      </Column>
      <Column header="Statut" :style="{ width: '11rem' }">
        <template #body="{ data }">
          <Select
            :model-value="data.status"
            :options="STATUS_OPTIONS"
            option-label="label"
            option-value="value"
            :loading="updatingId === data.id"
            :disabled="updatingId === data.id"
            :aria-label="`Statut du retour du ${formatDate(data.creation_datetime)}`"
            fluid
            @update:model-value="(status) => handleStatusChange(data, status)"
          />
        </template>
      </Column>
      <template #empty>
        <div class="text-center py-8 text-surface-500 dark:text-surface-400">
          Aucun retour pour le moment.
        </div>
      </template>
    </DataTable>

    <Paginator
      v-if="totalItems > ROWS_PER_PAGE"
      :rows="ROWS_PER_PAGE"
      :totalRecords="totalItems"
      :first="(page - 1) * ROWS_PER_PAGE"
      class="mt-4"
      @page="handlePageChange"
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref, watch } from 'vue'
import feedbackApi from '../../../api/admin/feedback.js'
import { FEEDBACK_MODULE_GROUPS, FEEDBACK_TYPE_OPTIONS } from '../../../constants/feedback.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { formatDate } from '../../../utils/date.js'

// Mirrors paginationItemsPerPage on the GetCollection. Server side paging, unlike the other admin
// tables: this one is never drained.
const ROWS_PER_PAGE = 25

// A sentinel rather than null, because PrimeVue reads a null model value as "nothing selected" and
// renders the field blank instead of showing the "all" option. Translated back to "send no filter"
// in load().
const ALL = 'all'

const STATUS_OPTIONS = [
  { value: 'new', label: 'Nouveau' },
  { value: 'in_progress', label: 'En cours' },
  { value: 'done', label: 'Traité' }
]

const STATUS_FILTER_OPTIONS = [{ value: ALL, label: 'Tous les statuts' }, ...STATUS_OPTIONS]

const TYPE_FILTER_OPTIONS = [{ value: ALL, label: 'Tous les types' }, ...FEEDBACK_TYPE_OPTIONS]

// Flat, unlike the drawer's grouped picker: PrimeVue needs every option inside a group once any
// group exists, so the "all" entry would need a group of its own and render an empty header above
// it. Grouping earns its place when someone is classifying their own report, not when an admin is
// narrowing a table.
const MODULE_FILTER_OPTIONS = [
  { value: ALL, label: 'Toutes les sections' },
  ...FEEDBACK_MODULE_GROUPS.flatMap((group) => group.items)
]

const TYPE_SEVERITY = {
  bug: 'danger',
  suggestion: 'info',
  question: 'warn',
  other: 'secondary'
}

useTitle('Retours - Admin - MusicAll')

const notificationStore = useNotificationStore()
const toast = useToast()

const feedbacks = ref([])
const totalItems = ref(0)
const page = ref(1)
const isLoading = ref(false)
const updatingId = ref(null)

const statusFilter = ref(ALL)
const typeFilter = ref(ALL)
const moduleFilter = ref(ALL)

/** The API has no "all" value: an unfiltered axis is an absent parameter. */
function asFilter(value) {
  return value === ALL ? null : value
}

async function load() {
  isLoading.value = true
  try {
    const data = await feedbackApi.list({
      page: page.value,
      status: asFilter(statusFilter.value),
      type: asFilter(typeFilter.value),
      module: asFilter(moduleFilter.value)
    })
    feedbacks.value = data.member
    totalItems.value = data.totalItems
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.detail || 'Impossible de charger les retours.',
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

// Filtering resets to the first page: staying on page 4 of a narrower result set shows an empty
// table and looks like a bug.
watch([statusFilter, typeFilter, moduleFilter], () => {
  page.value = 1
  load()
})

function handlePageChange(event) {
  page.value = event.page + 1
  load()
}

async function handleStatusChange(feedback, status) {
  if (status === feedback.status) {
    return
  }
  const previousStatus = feedback.status
  updatingId.value = feedback.id
  try {
    await feedbackApi.updateStatus(feedback.id, status)
    feedback.status = status
    // The navbar badge counts untriaged reports, so it has to follow a change made here rather than
    // wait for the next page load.
    await notificationStore.loadNotifications()
    toast.add({ severity: 'success', summary: 'Statut mis à jour', life: 2000 })
  } catch (e) {
    feedback.status = previousStatus
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    updatingId.value = null
  }
}

onMounted(load)
</script>
