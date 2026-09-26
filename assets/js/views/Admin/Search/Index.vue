<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Recherches</h1>
    <p class="-mt-4 text-surface-600 dark:text-surface-300">
      Les recherches de musiciens et de groupes, avec les filtres ou en langage naturel. Conservées six
      mois, sans compte ni adresse IP.
    </p>

    <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="figure in figures" :key="figure.label" class="rounded-xl bg-surface-0 dark:bg-surface-900 p-4 flex flex-col gap-1">
        <span class="text-sm text-surface-600 dark:text-surface-300">{{ figure.label }}</span>
        <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ figure.value }}</span>
        <span v-if="figure.hint" class="text-xs text-surface-600 dark:text-surface-300">{{ figure.hint }}</span>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <TimeSeriesChart
        title="Recherches avec filtres"
        icon="pi-search"
        color="#3a6589"
        :series-data="dashboardStore.timeSeries.musician_searches"
        :all-dates="allDates"
      />
      <TimeSeriesChart
        title="Recherches IA"
        icon="pi-sparkles"
        color="#a855f7"
        :series-data="dashboardStore.timeSeries.ai_searches"
        :all-dates="allDates"
      />
    </div>

    <Card>
      <template #title>
        <h2 class="m-0 text-base font-semibold">Les plus recherchées</h2>
      </template>
      <template #content>
        <DataTable :value="overview?.top_combinations ?? []" :loading="isLoadingOverview" stripedRows class="text-sm">
          <Column header="On cherche">
            <template #body="{ data }">{{ searchedForLabel(data.type) }}</template>
          </Column>
          <Column header="Instrument">
            <template #body="{ data }">{{ data.instrument_name ?? 'Tous' }}</template>
          </Column>
          <Column header="Ville">
            <template #body="{ data }">{{ data.location_name ?? 'Partout' }}</template>
          </Column>
          <Column field="searches" header="Recherches" />
          <Column field="visitors" header="Personnes" />
          <Column header="Sans résultat">
            <template #body="{ data }">
              <Tag v-if="data.zero_results > 0" :value="`${data.zero_results}`" severity="warn" />
              <span v-else>0</span>
            </template>
          </Column>
          <template #empty>
            <p class="m-0 text-center py-6 text-surface-600 dark:text-surface-300">Aucune recherche sur cette période.</p>
          </template>
        </DataTable>
      </template>
    </Card>

    <Card>
      <template #title>
        <h2 class="m-0 text-base font-semibold">Recherches IA</h2>
      </template>
      <template #content>
        <DataTable :value="aiSearches" :loading="isLoadingAi" stripedRows class="text-sm">
          <Column header="Le" :style="{ width: '10rem' }">
            <template #body="{ data }">{{ formatDate(data.search_datetime) }}</template>
          </Column>
          <Column field="query" header="Recherche" />
          <Column header="Résultat" :style="{ width: '10rem' }">
            <template #body="{ data }">
              <Tag :value="AI_OUTCOMES[data.outcome]?.label ?? data.outcome" :severity="AI_OUTCOMES[data.outcome]?.severity ?? 'secondary'" />
            </template>
          </Column>
          <Column header="Filtres produits">
            <template #body="{ data }">
              <span v-if="data.outcome !== 'filters'" class="text-surface-600 dark:text-surface-300">Aucun</span>
              <span v-else>
                {{ [searchedForLabel(data.type), data.instrument_name, ...data.style_names].filter(Boolean).join(' · ') }}
                <span v-if="data.latitude != null" class="text-surface-600 dark:text-surface-300"> · avec un lieu</span>
              </span>
            </template>
          </Column>
          <Column header="Connecté" :style="{ width: '7rem' }">
            <template #body="{ data }">{{ data.authenticated ? 'Oui' : 'Non' }}</template>
          </Column>
          <template #empty>
            <p class="m-0 text-center py-6 text-surface-600 dark:text-surface-300">Aucune recherche IA pour le moment.</p>
          </template>
        </DataTable>
        <Paginator
          v-if="aiTotal > AI_ROWS_PER_PAGE"
          :rows="AI_ROWS_PER_PAGE"
          :total-records="aiTotal"
          :first="(aiPage - 1) * AI_ROWS_PER_PAGE"
          class="mt-4"
          @page="handleAiPageChange"
        />
      </template>
    </Card>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import { eachDayOfInterval, format, subDays } from 'date-fns'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import adminSearchApi from '../../../api/admin/search.js'
import DateRangePicker from '../../../components/Admin/DateRangePicker.vue'
import TimeSeriesChart from '../../../components/Admin/TimeSeriesChart.vue'
import { useAdminDashboardStore } from '../../../store/admin/dashboard.js'
import { AI_OUTCOMES, percentOf, searchedForLabel } from '../../../utils/adminSearch.js'

/** What people search for (#1075): the filters searches over a period, and the AI questions one by one. */
useTitle('Recherches - Administration MusicAll')

const AI_ROWS_PER_PAGE = 25

const toast = useToast()
const dashboardStore = useAdminDashboardStore()

const today = new Date()
const dateFrom = ref(subDays(today, 30))
const dateTo = ref(today)
const overview = ref(null)
const isLoadingOverview = ref(false)
const aiSearches = ref([])
const aiTotal = ref(0)
const aiPage = ref(1)
const isLoadingAi = ref(false)

const allDates = computed(() =>
  eachDayOfInterval({ start: dateFrom.value, end: dateTo.value }).map((d) =>
    format(d, 'yyyy-MM-dd')
  )
)

const figures = computed(() => {
  const o = overview.value
  if (!o) return []
  return [
    { label: 'Recherches avec filtres', value: o.searches },
    {
      label: 'Sans résultat',
      value: percentOf(o.zero_result_searches, o.searches),
      hint: `${o.zero_result_searches} recherches`
    },
    { label: 'Recherches IA', value: o.ai_searches },
    {
      label: 'IA sans filtre exploitable',
      value: percentOf(o.ai_nothing + o.ai_failed, o.ai_searches),
      hint: `${o.ai_nothing} rien compris, ${o.ai_failed} échecs`
    }
  ]
})

function formatDate(value) {
  return format(new Date(value), 'dd/MM/yyyy HH:mm')
}

function showError(detail) {
  toast.add({ severity: 'error', summary: 'Erreur', detail, life: 4000 })
}

async function loadOverview() {
  const from = format(dateFrom.value, 'yyyy-MM-dd')
  const to = format(dateTo.value, 'yyyy-MM-dd')
  dashboardStore.loadTimeSeries('musician_searches', from, to)
  dashboardStore.loadTimeSeries('ai_searches', from, to)
  isLoadingOverview.value = true
  try {
    overview.value = await adminSearchApi.getOverview(from, to)
  } catch {
    showError('Impossible de charger les recherches.')
  } finally {
    isLoadingOverview.value = false
  }
}

async function loadAiSearches() {
  isLoadingAi.value = true
  try {
    const data = await adminSearchApi.listAiSearches(aiPage.value)
    aiSearches.value = data.member
    aiTotal.value = data.totalItems
  } catch {
    showError('Impossible de charger les recherches IA.')
  } finally {
    isLoadingAi.value = false
  }
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  loadOverview()
}

function handleAiPageChange(event) {
  aiPage.value = event.page + 1
  loadAiSearches()
}

onMounted(() => {
  loadOverview()
  loadAiSearches()
})
</script>
