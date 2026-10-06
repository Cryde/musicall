<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Signalements</h1>
      <Tag v-if="totalItems > 0" :value="`${totalItems} signalement(s)`" severity="secondary" />
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
    </div>

    <div v-if="isLoading && !reports.length" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-16 bg-surface-100 dark:bg-surface-800 animate-pulse rounded" />
    </div>

    <DataTable v-else :value="reports" stripedRows class="text-sm">
      <Column field="creation_datetime" header="Reçu le" :style="{ width: '10rem' }">
        <template #body="{ data }">{{ formatDate(data.creation_datetime) }}</template>
      </Column>
      <Column header="Type" :style="{ width: '10rem' }">
        <template #body="{ data }">
          <Tag :value="reportTargetTypeLabel(data.target_type)" severity="secondary" />
        </template>
      </Column>
      <Column header="Motif" :style="{ width: '10rem' }">
        <template #body="{ data }">{{ reportReasonLabel(data.reason) }}</template>
      </Column>
      <Column header="Contenu">
        <template #body="{ data }">
          <p class="line-clamp-2">{{ reportExcerpt(data.snapshot_text) || '-' }}</p>
          <p v-if="data.outcome" class="text-xs text-surface-600 dark:text-surface-400 mt-1">
            {{ reportOutcomeLabel(data.outcome) }}
            <span v-if="data.resolved_by_username"> par {{ data.resolved_by_username }}</span>
          </p>
        </template>
      </Column>
      <Column header="Auteur" :style="{ width: '11rem' }">
        <template #body="{ data }">
          <div v-if="data.target_author" class="flex flex-wrap items-center gap-1">
            <span>{{ data.target_author.username }}</span>
            <Tag v-if="data.target_author.is_suspended" value="suspendu" severity="danger" />
          </div>
          <span v-else class="text-surface-600 dark:text-surface-400">-</span>
        </template>
      </Column>
      <Column header="Signalé par" :style="{ width: '10rem' }">
        <template #body="{ data }">{{ data.reporter.username }}</template>
      </Column>
      <Column header="En attente" :style="{ width: '7rem' }">
        <template #body="{ data }">
          <Badge
            v-if="data.pending_report_count > 0"
            :value="data.pending_report_count"
            severity="warn"
            :aria-label="`${data.pending_report_count} signalement(s) en attente sur ce contenu`"
          />
        </template>
      </Column>
      <Column :style="{ width: '4rem' }">
        <template #body="{ data }">
          <RouterLink
            :to="{ name: 'admin_reports_show', params: { id: data.id } }"
            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
            :aria-label="`Voir le signalement du ${formatDate(data.creation_datetime)}`"
          >
            <i class="pi pi-arrow-right" aria-hidden="true" />
          </RouterLink>
        </template>
      </Column>
      <template #empty>
        <div class="text-center py-8 text-surface-500 dark:text-surface-400">
          Aucun signalement pour le moment.
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
import Badge from 'primevue/badge'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref, watch } from 'vue'
import adminReportApi from '../../../api/admin/report.js'
import { formatDate } from '../../../utils/date.js'
import {
  reportExcerpt,
  reportOutcomeLabel,
  reportReasonLabel,
  reportTargetTypeLabel
} from '../../../utils/reportTarget.js'

// Mirrors paginationItemsPerPage on the GetCollection.
const ROWS_PER_PAGE = 25

// A sentinel rather than null, because PrimeVue renders a null model value as an empty field.
const ALL = 'all'

const STATUS_FILTER_OPTIONS = [
  { value: 'pending', label: 'En attente' },
  { value: 'resolved', label: 'Traités' },
  { value: ALL, label: 'Tous' }
]

useTitle('Signalements - Admin - MusicAll')

const toast = useToast()

const reports = ref([])
const totalItems = ref(0)
const page = ref(1)
const isLoading = ref(false)
const statusFilter = ref('pending')

async function load() {
  isLoading.value = true
  try {
    const data = await adminReportApi.list({
      page: page.value,
      status: statusFilter.value === ALL ? null : statusFilter.value
    })
    reports.value = data.member
    totalItems.value = data.totalItems
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.detail || 'Impossible de charger les signalements.',
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

watch(statusFilter, () => {
  page.value = 1
  load()
})

function handlePageChange(event) {
  page.value = event.page + 1
  load()
}

onMounted(load)
</script>
