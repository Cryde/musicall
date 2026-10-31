<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Band Space</h1>
    <p class="-mt-4 text-surface-600 dark:text-surface-300">
      Comment les espaces de groupe sont utilisés. Les espaces en attente de suppression ne sont
      comptés nulle part ailleurs que dans leur propre chiffre.
    </p>

    <section class="flex flex-col gap-3">
      <h2 class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-100">Aujourd'hui</h2>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="figure in currentFigures" :key="figure.label" class="rounded-xl bg-surface-0 dark:bg-surface-900 p-4 flex flex-col gap-1">
          <span class="text-sm text-surface-600 dark:text-surface-300">{{ figure.label }}</span>
          <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ figure.value }}</span>
          <span v-if="figure.hint" class="text-xs text-surface-600 dark:text-surface-300">{{ figure.hint }}</span>
        </div>
      </div>
    </section>

    <section class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-100">Sur la période</h2>
        <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="figure in periodFigures" :key="figure.label" class="rounded-xl bg-surface-0 dark:bg-surface-900 p-4 flex flex-col gap-1">
          <span class="text-sm text-surface-600 dark:text-surface-300">{{ figure.label }}</span>
          <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ figure.value }}</span>
          <span v-if="figure.hint" class="text-xs text-surface-600 dark:text-surface-300">{{ figure.hint }}</span>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <TimeSeriesChart
        title="Band Spaces créés"
        icon="pi-objects-column"
        color="#f59e0b"
        :series-data="dashboardStore.timeSeries.band_spaces"
        :all-dates="allDates"
      />
      <TimeSeriesChart
        title="Messages de discussion"
        icon="pi-comments"
        color="#3a6589"
        :series-data="dashboardStore.timeSeries.band_chat_messages"
        :all-dates="allDates"
      />
    </div>

    <Card>
      <template #title>
        <h2 class="m-0 text-base font-semibold">Modules utilisés sur la période</h2>
      </template>
      <template #content>
        <DataTable :value="modules" :loading="isLoading" stripedRows class="text-sm">
          <Column header="Module">
            <template #body="{ data }">{{ BAND_SPACE_MODULE_LABELS[data.module] ?? data.module }}</template>
          </Column>
          <Column header="Espaces">
            <template #body="{ data }">
              {{ data.spaces }}
              <span class="text-surface-600 dark:text-surface-300">({{ percentOf(data.spaces, overview?.spaces ?? 0) }})</span>
            </template>
          </Column>
          <Column field="activities" header="Activités" />
        </DataTable>
      </template>
    </Card>

    <Card>
      <template #title>
        <h2 class="m-0 text-base font-semibold">Stockage le plus utilisé</h2>
      </template>
      <template #content>
        <DataTable :value="overview?.top_storage ?? []" :loading="isLoading" stripedRows class="text-sm">
          <Column field="name" header="Band Space" />
          <Column header="Utilisé">
            <template #body="{ data }">{{ formatBytes(data.bytes) }}</template>
          </Column>
          <Column header="Quota">
            <template #body="{ data }">{{ formatBytes(data.quota_bytes) }}</template>
          </Column>
          <Column header="Part du quota">
            <template #body="{ data }">
              <Tag :value="percentOf(data.bytes, data.quota_bytes)" :severity="quotaSeverity(data.bytes, data.quota_bytes)" />
            </template>
          </Column>
          <template #empty>
            <p class="m-0 text-center py-6 text-surface-600 dark:text-surface-300">Aucun fichier pour le moment.</p>
          </template>
        </DataTable>
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
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import adminBandSpaceApi from '../../../api/admin/bandSpace.js'
import DateRangePicker from '../../../components/Admin/DateRangePicker.vue'
import TimeSeriesChart from '../../../components/Admin/TimeSeriesChart.vue'
import { useAdminDashboardStore } from '../../../store/admin/dashboard.js'
import { BAND_SPACE_MODULE_LABELS, quotaSeverity } from '../../../utils/adminBandSpace.js'
import { percentOf } from '../../../utils/adminSearch.js'
import { formatBytes } from '../../../utils/formatBytes.js'

useTitle('Band Space - Administration MusicAll')

const toast = useToast()
const dashboardStore = useAdminDashboardStore()
const decimal = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })

const today = new Date()
const dateFrom = ref(subDays(today, 30))
const dateTo = ref(today)
const overview = ref(null)
const isLoading = ref(false)

const allDates = computed(() =>
  eachDayOfInterval({ start: dateFrom.value, end: dateTo.value }).map((d) =>
    format(d, 'yyyy-MM-dd')
  )
)

const currentFigures = computed(() => {
  const o = overview.value
  if (!o) return []
  return [
    {
      label: 'Band Spaces',
      value: o.spaces,
      hint: `${o.pending_deletion_spaces} en attente de suppression`
    },
    {
      label: 'Actifs',
      value: percentOf(o.active_spaces, o.spaces),
      hint: `${o.active_spaces} avec une activité ou un message ces ${o.active_window_days} derniers jours`
    },
    {
      label: 'En sommeil',
      value: percentOf(o.dormant_spaces, o.spaces),
      hint: `${o.dormant_spaces} sans rien depuis ${o.active_window_days} jours`
    },
    {
      label: 'Membres par espace',
      value: decimal.format(o.average_members),
      hint: `en moyenne, médiane ${decimal.format(o.median_members)}`
    },
    {
      label: 'Seuls dans leur espace',
      value: percentOf(o.solo_spaces, o.spaces),
      hint: `${spaceCount(o.solo_spaces)} avec un seul membre`
    },
    {
      label: 'Stockage utilisé',
      value: formatBytes(o.storage_bytes),
      hint: 'fichiers non archivés, toutes versions'
    }
  ]
})

const periodFigures = computed(() => {
  const o = overview.value
  if (!o) return []
  return [
    {
      label: 'Messages de discussion',
      value: o.chat_messages,
      hint: `dans ${spaceCount(o.chat_spaces)}`
    },
    {
      label: 'Espaces qui discutent',
      value: percentOf(o.chat_spaces, o.spaces),
      hint: `${o.chat_spaces} sur ${o.spaces}`
    }
  ]
})

const modules = computed(() =>
  [...(overview.value?.modules ?? [])].sort(
    (a, b) => b.spaces - a.spaces || b.activities - a.activities
  )
)

/** French plural: « 0 espace », « 1 espace », « 2 espaces ». */
function spaceCount(count) {
  return `${count} ${count > 1 ? 'espaces' : 'espace'}`
}

async function loadOverview() {
  const from = format(dateFrom.value, 'yyyy-MM-dd')
  const to = format(dateTo.value, 'yyyy-MM-dd')
  dashboardStore.loadTimeSeries('band_spaces', from, to)
  dashboardStore.loadTimeSeries('band_chat_messages', from, to)
  isLoading.value = true
  try {
    overview.value = await adminBandSpaceApi.getOverview(from, to)
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les Band Spaces.',
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  loadOverview()
}

onMounted(loadOverview)
</script>
