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
      <!-- Activity Trends -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard
          title="Inscriptions"
          icon="pi-user-plus"
          :today="metrics.registrations_today"
          :week="metrics.registrations7_days"
          :month="metrics.registrations30_days"
          :trend-percent="metrics.registrations_trend_percent"
        />
        <MetricCard
          title="Connexions"
          icon="pi-sign-in"
          :today="metrics.logins_today"
          :week="metrics.logins7_days"
          :month="metrics.logins30_days"
          :trend-percent="metrics.logins_trend_percent"
        />
        <MetricCard
          title="Messages"
          icon="pi-envelope"
          :today="metrics.messages_today"
          :week="metrics.messages7_days"
          :month="metrics.messages30_days"
          :trend-percent="metrics.messages_trend_percent"
        />
        <MetricCard
          title="Publications"
          icon="pi-file-edit"
          :today="metrics.publications_today"
          :week="metrics.publications7_days"
          :month="metrics.publications30_days"
          :trend-percent="metrics.publications_trend_percent"
        />
      </div>

      <!-- Engagement & Retention -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

        <!-- Totals -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-database text-primary" />
              <span>Totaux</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-surface-600 dark:text-surface-400">Utilisateurs</span>
                <span class="text-2xl font-bold">{{ formatNumber(metrics.total_users) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-surface-600 dark:text-surface-400">Publications</span>
                <span class="text-2xl font-bold">{{ formatNumber(metrics.total_publications) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-surface-600 dark:text-surface-400">Messages</span>
                <span class="text-2xl font-bold">{{ formatNumber(metrics.total_messages) }}</span>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Content Overview -->
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
            <div v-if="Object.keys(metrics.publications_by_type).length > 0" class="space-y-3">
              <div
                v-for="(count, category) in metrics.publications_by_type"
                :key="category"
                class="flex items-center justify-between"
              >
                <span class="text-surface-600 dark:text-surface-400 capitalize">{{ category }}</span>
                <Badge :value="count" severity="secondary" />
              </div>
            </div>
            <div v-else class="text-surface-400 text-center py-4">
              Aucune donnée disponible
            </div>
          </template>
        </Card>

        <!-- Top Content This Week -->
        <Card>
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-star text-primary" />
              <span>Top contenu cette semaine</span>
            </div>
          </template>
          <template #content>
            <div v-if="metrics.top_content_this_week.length > 0" class="space-y-3">
              <div
                v-for="(content, index) in metrics.top_content_this_week"
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
              Aucune publication cette semaine
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
import { computed, onMounted } from 'vue'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useAdminDashboardStore } from '../../store/admin/dashboard.js'
import MetricCard from '../../components/Admin/MetricCard.vue'
import ComingSoonBadge from '../../components/Admin/ComingSoonBadge.vue'

const notificationStore = useNotificationStore()
const dashboardStore = useAdminDashboardStore()

const metrics = computed(() => dashboardStore.generalMetrics)

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

async function refreshData() {
  await Promise.all([
    notificationStore.loadNotifications(),
    dashboardStore.loadGeneralMetrics()
  ])
}

onMounted(async () => {
  await refreshData()
})
</script>
