<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Gestion du forum
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <AdminModuleCard
        label="Modération du forum"
        description="File de modération des sujets et posts signalés"
        icon="pi-shield"
        color="#06b6d4"
        coming-soon
      />
    </div>

    <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <TimeSeriesChart
        title="Posts forum"
        icon="pi-comments"
        color="#06b6d4"
        :series-data="dashboardStore.timeSeries.forum_posts"
        :all-dates="allDates"
      />

      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-comments text-primary" />
            <span>Activité du forum</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview" class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-surface-600 dark:text-surface-400">Sujets créés</span>
              <Badge :value="contentOverview.forum_topics_count" severity="secondary" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-surface-600 dark:text-surface-400">Posts publiés</span>
              <Badge :value="contentOverview.forum_posts_count" severity="secondary" />
            </div>
          </div>
          <div v-else class="text-surface-400 text-center py-4">
            Aucune donnée sur cette période
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
  dashboardStore.loadTimeSeries('forum_posts', from, to)
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
