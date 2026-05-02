<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Tableau de bord</h1>
      <Button
        icon="pi pi-refresh"
        severity="secondary"
        text
        rounded
        :loading="dashboardStore.isLoadingGeneral"
        @click="refreshData"
      />
    </div>

    <!-- Module entry points -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <AdminModuleCard
        v-for="module in ADMIN_MODULES"
        :key="module.key"
        :label="module.label"
        :description="module.description"
        :icon="module.icon"
        :color="module.color"
        :route="module.route"
        :badge-count="badgeCountFor(module)"
      />
    </div>

    <!-- Loading State -->
    <div v-if="dashboardStore.isLoadingGeneral" class="flex justify-center py-8">
      <ProgressSpinner style="width: 50px; height: 50px" />
    </div>

    <!-- Error State -->
    <Message v-else-if="dashboardStore.generalError" severity="error" :closable="false">
      {{ dashboardStore.generalError }}
    </Message>

    <!-- Metrics Content -->
    <template v-else-if="metrics">
      <!-- Totals Summary Bar (global, not date-filtered) -->
      <div class="bg-surface-0 dark:bg-surface-900 shadow-sm p-6 rounded-2xl flex flex-col sm:flex-row gap-4">
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Utilisateurs</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ formatNumber(metrics.total_users) }}</div>
        </div>
        <div class="sm:w-px w-full max-w-xs sm:max-w-none mx-auto sm:mx-0 sm:h-auto h-px bg-surface-200 dark:bg-surface-700 self-stretch" />
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Publications</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ formatNumber(metrics.total_publications) }}</div>
        </div>
        <div class="sm:w-px w-full max-w-xs sm:max-w-none mx-auto sm:mx-0 sm:h-auto h-px bg-surface-200 dark:bg-surface-700 self-stretch" />
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Messages</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ formatNumber(metrics.total_messages) }}</div>
        </div>
      </div>

      <!-- Date-filtered section -->
      <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

      <!-- Activity Trends - Charts -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <TimeSeriesChart
          title="Inscriptions"
          icon="pi-user-plus"
          color="#6366f1"
          :series-data="dashboardStore.timeSeries.registrations"
          :all-dates="allDates"
        />
        <TimeSeriesChart
          title="Connexions"
          icon="pi-sign-in"
          color="#22c55e"
          :series-data="dashboardStore.timeSeries.logins"
          :all-dates="allDates"
        />
        <TimeSeriesChart
          title="Messages"
          icon="pi-envelope"
          color="#f59e0b"
          :series-data="dashboardStore.timeSeries.messages"
          :all-dates="allDates"
        />
        <TimeSeriesChart
          title="Annonces musiciens"
          icon="pi-megaphone"
          color="#ec4899"
          :series-data="dashboardStore.timeSeries.musician_announces"
          :all-dates="allDates"
        />
      </div>

      <!-- Content Overview (date-filtered) -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Musician Announces -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-megaphone text-primary" />
              <span>Annonces musiciens</span>
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

        <!-- Top Instruments & Styles -->
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

    </template>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { format, subDays, eachDayOfInterval } from 'date-fns'
import { ADMIN_MODULES } from '../../constants/admin.js'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useAdminDashboardStore } from '../../store/admin/dashboard.js'
import AdminModuleCard from '../../components/Admin/AdminModuleCard.vue'
import DateRangePicker from '../../components/Admin/DateRangePicker.vue'
import TimeSeriesChart from '../../components/Admin/TimeSeriesChart.vue'

const notificationStore = useNotificationStore()
const dashboardStore = useAdminDashboardStore()

const metrics = computed(() => dashboardStore.generalMetrics)
const contentOverview = computed(() => dashboardStore.contentOverview)

const today = new Date()
const dateFrom = ref(subDays(today, 30))
const dateTo = ref(today)

const allDates = computed(() =>
  eachDayOfInterval({ start: dateFrom.value, end: dateTo.value }).map((d) => format(d, 'yyyy-MM-dd'))
)

function badgeCountFor(module) {
  if (module.key === 'publications') {
    return notificationStore.pendingPublications + notificationStore.pendingGalleries
  }
  return 0
}

function formatNumber(num) {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M'
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'k'
  }
  return num.toString()
}

function formatDate(date) {
  return format(date, 'yyyy-MM-dd')
}

function loadDateFilteredData() {
  const from = formatDate(dateFrom.value)
  const to = formatDate(dateTo.value)
  const metricNames = ['registrations', 'logins', 'messages', 'musician_announces']
  metricNames.forEach((metric) => dashboardStore.loadTimeSeries(metric, from, to))
  dashboardStore.loadContentOverview(from, to)
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  loadDateFilteredData()
}

async function refreshData() {
  await Promise.all([
    notificationStore.loadNotifications(),
    dashboardStore.loadGeneralMetrics()
  ])
  loadDateFilteredData()
}

onMounted(async () => {
  await refreshData()
})
</script>
