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

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <RouterLink
        :to="{ name: 'admin_publications_pending' }"
        class="flex items-center justify-between p-4 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
      >
        <div class="flex items-center gap-3">
          <i class="pi pi-file-edit text-xl text-primary" />
          <span class="font-medium">Publications en attente</span>
        </div>
        <Badge v-if="notificationStore.pendingPublications > 0" :value="notificationStore.pendingPublications" severity="warn" />
      </RouterLink>

      <RouterLink
        :to="{ name: 'admin_galleries_pending' }"
        class="flex items-center justify-between p-4 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
      >
        <div class="flex items-center gap-3">
          <i class="pi pi-images text-xl text-primary" />
          <span class="font-medium">Galeries en attente</span>
        </div>
        <Badge v-if="notificationStore.pendingGalleries > 0" :value="notificationStore.pendingGalleries" severity="warn" />
      </RouterLink>

      <RouterLink
        :to="{ name: 'admin_users_dashboard' }"
        class="flex items-center justify-between p-4 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
      >
        <div class="flex items-center gap-3">
          <i class="pi pi-users text-xl text-primary" />
          <span class="font-medium">Gestion des utilisateurs</span>
        </div>
        <i class="pi pi-arrow-right text-surface-400" />
      </RouterLink>
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
        <TimeSeriesChart
          title="Posts forum"
          icon="pi-comments"
          color="#06b6d4"
          :series-data="dashboardStore.timeSeries.forum_posts"
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
        <!-- Publications by Type -->
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

        <!-- Publications by Format -->
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

        <!-- Top Content -->
        <Card>
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

        <!-- Forum -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-comments text-primary" />
              <span>Forum</span>
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
          </template>
        </Card>

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

      <!-- Engagement & Retention -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- DAU/MAU Ratio -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-chart-line text-primary" />
              <span>Engagement</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span
                  v-tooltip.top="'Utilisateurs actifs aujourd\'hui / Utilisateurs actifs ce mois. Plus le ratio est élevé, plus les utilisateurs reviennent souvent.'"
                  class="text-surface-600 dark:text-surface-400 cursor-help border-b border-dashed border-surface-400"
                >
                  Ratio DAU/MAU
                </span>
                <span v-if="metrics.dau_mau_ratio !== null" class="text-2xl font-bold text-primary">
                  {{ metrics.dau_mau_ratio }}%
                </span>
                <ComingSoonBadge v-else />
              </div>
              <div class="flex items-center justify-between">
                <span
                  v-tooltip.top="'Temps moyen entre l\'inscription et la première action (message, publication, etc.)'"
                  class="text-surface-600 dark:text-surface-400 cursor-help border-b border-dashed border-surface-400"
                >
                  Temps avant 1re action
                </span>
                <ComingSoonBadge />
              </div>
              <div class="flex items-center justify-between">
                <span
                  v-tooltip.top="'Pourcentage d\'utilisateurs ayant initié au moins une conversation'"
                  class="text-surface-600 dark:text-surface-400 cursor-help border-b border-dashed border-surface-400"
                >
                  Ratio conversations
                </span>
                <ComingSoonBadge />
              </div>
            </div>
          </template>
        </Card>

        <!-- Retention -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-replay text-primary" />
              <span>Rétention</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span
                  v-tooltip.top="'Pourcentage d\'utilisateurs inscrits il y a 7-14 jours qui se sont reconnectés depuis'"
                  class="text-surface-600 dark:text-surface-400 cursor-help border-b border-dashed border-surface-400"
                >
                  Rétention 7 jours
                </span>
                <span v-if="metrics.retention7_days !== null" :class="getRetentionClass(metrics.retention7_days)" class="text-2xl font-bold">
                  {{ metrics.retention7_days }}%
                </span>
                <span v-else class="text-surface-400">-</span>
              </div>
              <div class="flex items-center justify-between">
                <span
                  v-tooltip.top="'Pourcentage d\'utilisateurs inscrits il y a 30-60 jours qui se sont reconnectés depuis'"
                  class="text-surface-600 dark:text-surface-400 cursor-help border-b border-dashed border-surface-400"
                >
                  Rétention 30 jours
                </span>
                <span v-if="metrics.retention30_days !== null" :class="getRetentionClass(metrics.retention30_days)" class="text-2xl font-bold">
                  {{ metrics.retention30_days }}%
                </span>
                <span v-else class="text-surface-400">-</span>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Popular Searches (Coming Soon) -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2 text-base">
            <i class="pi pi-search text-primary" />
            <span>Recherches populaires</span>
            <ComingSoonBadge />
          </div>
        </template>
        <template #content>
          <div class="text-surface-400 text-center py-4">
            Le suivi des recherches n'est pas encore implémenté
          </div>
        </template>
      </Card>
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
import { useNotificationStore } from '../../store/notification/notification.js'
import { useAdminDashboardStore } from '../../store/admin/dashboard.js'
import DateRangePicker from '../../components/Admin/DateRangePicker.vue'
import TimeSeriesChart from '../../components/Admin/TimeSeriesChart.vue'
import ComingSoonBadge from '../../components/Admin/ComingSoonBadge.vue'

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

function formatNumber(num) {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M'
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'k'
  }
  return num.toString()
}

function getRetentionClass(value) {
  if (value >= 50) return 'text-green-500'
  if (value >= 25) return 'text-yellow-500'
  return 'text-red-500'
}

function formatDate(date) {
  return format(date, 'yyyy-MM-dd')
}

function loadDateFilteredData() {
  const from = formatDate(dateFrom.value)
  const to = formatDate(dateTo.value)
  const metricNames = ['registrations', 'logins', 'messages', 'publications', 'comments', 'forum_posts', 'musician_announces']
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
