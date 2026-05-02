<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Gestion de l'annuaire
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <AdminModuleCard
        label="Annonces musiciens"
        description="Liste et modération des annonces (cherche musicien / cherche groupe)"
        icon="pi-megaphone"
        color="#22c55e"
        coming-soon
      />
      <AdminModuleCard
        label="Professeurs"
        description="Profils de professeurs inscrits sur la plateforme"
        icon="pi-graduation-cap"
        color="#a855f7"
        coming-soon
      />
    </div>

    <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

    <TimeSeriesChart
      title="Annonces musiciens"
      icon="pi-megaphone"
      color="#ec4899"
      :series-data="dashboardStore.timeSeries.musician_announces"
      :all-dates="allDates"
    />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-megaphone text-primary" />
            <span>Annonces par type</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview" class="space-y-4">
            <div v-if="Object.keys(contentOverview.announces_by_type).length > 0" class="space-y-2">
              <div
                v-for="(count, type) in contentOverview.announces_by_type"
                :key="type"
                class="flex items-center justify-between"
              >
                <span class="text-surface-600 dark:text-surface-400">{{ type === 'musician' ? 'Cherche musicien' : 'Cherche groupe' }}</span>
                <Badge :value="count" severity="secondary" />
              </div>
            </div>
            <div v-else class="text-surface-400 text-center py-2">
              Aucune annonce sur cette période
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-music text-primary" />
            <span>Top instruments &amp; styles</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview" class="space-y-4">
            <div v-if="contentOverview.top_instruments.length > 0">
              <p class="text-sm font-medium text-surface-500 mb-2">Instruments</p>
              <div class="flex gap-2 flex-wrap">
                <Tag v-for="item in contentOverview.top_instruments" :key="item.name" :value="`${item.name} (${item.count})`" severity="info" />
              </div>
            </div>
            <div v-if="contentOverview.top_styles.length > 0">
              <p class="text-sm font-medium text-surface-500 mb-2">Styles</p>
              <div class="flex gap-2 flex-wrap">
                <Tag v-for="item in contentOverview.top_styles" :key="item.name" :value="`${item.name} (${item.count})`" severity="warn" />
              </div>
            </div>
            <div v-if="contentOverview.top_instruments.length === 0 && contentOverview.top_styles.length === 0" class="text-surface-400 text-center py-2">
              Aucune donnée sur cette période
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { eachDayOfInterval, format, subDays } from 'date-fns'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import AdminModuleCard from '../../../components/Admin/AdminModuleCard.vue'
import DateRangePicker from '../../../components/Admin/DateRangePicker.vue'
import TimeSeriesChart from '../../../components/Admin/TimeSeriesChart.vue'
import { useAdminDashboardStore } from '../../../store/admin/dashboard.js'

const dashboardStore = useAdminDashboardStore()

const contentOverview = computed(() => dashboardStore.contentOverview)

const today = new Date()
const dateFrom = ref(subDays(today, 30))
const dateTo = ref(today)

const allDates = computed(() =>
  eachDayOfInterval({ start: dateFrom.value, end: dateTo.value }).map((d) =>
    format(d, 'yyyy-MM-dd')
  )
)

function loadMetrics() {
  const from = format(dateFrom.value, 'yyyy-MM-dd')
  const to = format(dateTo.value, 'yyyy-MM-dd')
  dashboardStore.loadTimeSeries('musician_announces', from, to)
  dashboardStore.loadContentOverview(from, to)
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  loadMetrics()
}

onMounted(() => {
  loadMetrics()
})
</script>
