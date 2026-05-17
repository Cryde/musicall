<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Gestion des publications
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <AdminModuleCard
        label="Publications en attente"
        description="Modérer les publications soumises par les utilisateurs"
        icon="pi-file-edit"
        color="#6366f1"
        route="admin_publications_pending"
        :badge-count="notificationStore.pendingPublications"
      />

      <AdminModuleCard
        label="Galeries en attente"
        description="Modérer les galeries d'images soumises"
        icon="pi-images"
        color="#06b6d4"
        route="admin_galleries_pending"
        :badge-count="notificationStore.pendingGalleries"
      />

      <AdminModuleCard
        label="Tags"
        description="Gérer les tags des publications et des cours"
        icon="pi-hashtag"
        color="#8b5cf6"
        route="admin_publications_tags"
      />

      <AdminModuleCard
        label="Supprimer une publication"
        description="Rechercher et supprimer définitivement une publication publiée"
        icon="pi-trash"
        color="#ef4444"
        route="admin_publications_delete"
      />

      <AdminModuleCard
        label="Cours"
        description="Les cours partagent la même infrastructure que les publications"
        icon="pi-book"
        color="#22c55e"
        coming-soon
      />

      <AdminModuleCard
        label="Derniers commentaires"
        description="Vue d'ensemble des commentaires récents et leur modération"
        icon="pi-comment"
        color="#f59e0b"
        coming-soon
      />
    </div>

    <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <TimeSeriesChart
        title="Publications"
        icon="pi-file-edit"
        color="#ef4444"
        :series-data="dashboardStore.timeSeries.publications"
        :all-dates="allDates"
      />
      <TimeSeriesChart
        title="Commentaires"
        icon="pi-comment"
        color="#8b5cf6"
        :series-data="dashboardStore.timeSeries.comments"
        :all-dates="allDates"
      />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-chart-pie text-primary" />
            <span>Publications par catégorie</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview && Object.keys(contentOverview.publications_by_type).length > 0" class="space-y-3">
            <div
              v-for="(count, category) in contentOverview.publications_by_type"
              :key="category"
              class="flex items-center justify-between"
            >
              <span class="text-surface-600 dark:text-surface-400 capitalize">{{ category }}</span>
              <Badge :value="count" severity="secondary" />
            </div>
          </div>
          <div v-else class="text-surface-400 text-center py-4">
            Aucune donnée sur cette période
          </div>
        </template>
      </Card>

      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-file text-primary" />
            <span>Publications par format</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview && Object.keys(contentOverview.publications_by_format).length > 0" class="space-y-3">
            <div
              v-for="(count, format) in contentOverview.publications_by_format"
              :key="format"
              class="flex items-center justify-between"
            >
              <span class="text-surface-600 dark:text-surface-400 capitalize">{{ format }}</span>
              <Badge :value="count" severity="secondary" />
            </div>
          </div>
          <div v-else class="text-surface-400 text-center py-4">
            Aucune donnée sur cette période
          </div>
        </template>
      </Card>

      <Card class="lg:col-span-2">
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-star text-primary" />
            <span>Top contenu</span>
          </div>
        </template>
        <template #content>
          <div v-if="dashboardStore.isLoadingContentOverview" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="contentOverview && contentOverview.top_content.length > 0" class="space-y-3">
            <div
              v-for="(content, index) in contentOverview.top_content"
              :key="content.id"
              class="flex items-center gap-3"
            >
              <span class="text-surface-400 font-mono">{{ index + 1 }}.</span>
              <div class="flex-1 min-w-0">
                <p class="truncate font-medium">{{ content.title }}</p>
                <div class="flex items-center gap-2 text-sm text-surface-500">
                  <Tag :value="content.type" size="small" />
                  <span>{{ content.views }} vues</span>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-surface-400 text-center py-4">
            Aucune publication sur cette période
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
import { useNotificationStore } from '../../../store/notification/notification.js'

const notificationStore = useNotificationStore()
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
  dashboardStore.loadTimeSeries('publications', from, to)
  dashboardStore.loadTimeSeries('comments', from, to)
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
