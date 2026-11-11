<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Gestion des utilisateurs</h1>
      <Button
        icon="pi pi-refresh"
        severity="secondary"
        text
        rounded
        :loading="dashboardStore.isLoadingUsers"
        @click="refreshData"
      />
    </div>

    <UserSearchPanel />

    <!-- Loading State -->
    <div v-if="dashboardStore.isLoadingUsers" class="flex justify-center py-8">
      <ProgressSpinner style="width: 50px; height: 50px" />
    </div>

    <!-- Error State -->
    <Message v-else-if="dashboardStore.usersError" severity="error" :closable="false">
      {{ dashboardStore.usersError }}
    </Message>

    <!-- Metrics Content -->
    <template v-else-if="metrics">
      <!-- Global Metrics Summary Bar -->
      <div class="bg-surface-0 dark:bg-surface-900 shadow-sm p-6 rounded-2xl flex flex-col sm:flex-row gap-4">
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Utilisateurs</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ metrics.total_users }}</div>
        </div>
        <div class="sm:w-px w-full max-w-xs sm:max-w-none mx-auto sm:mx-0 sm:h-auto h-px bg-surface-200 dark:bg-surface-700 self-stretch" />
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Comptes non confirmés</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ metrics.unconfirmed_accounts }}</div>
        </div>
        <div class="sm:w-px w-full max-w-xs sm:max-w-none mx-auto sm:mx-0 sm:h-auto h-px bg-surface-200 dark:bg-surface-700 self-stretch" />
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Profils musiciens</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ metrics.total_musician_profiles }}</div>
        </div>
        <div class="sm:w-px w-full max-w-xs sm:max-w-none mx-auto sm:mx-0 sm:h-auto h-px bg-surface-200 dark:bg-surface-700 self-stretch" />
        <div class="flex flex-col items-center gap-2 flex-1">
          <span class="text-surface-500 dark:text-surface-300 font-normal leading-tight">Profils professeur</span>
          <div class="text-surface-900 dark:text-surface-0 font-semibold text-3xl! leading-tight! text-center w-full">{{ metrics.total_teacher_profiles }}</div>
        </div>
      </div>

      <!-- Date-filtered section -->
      <DateRangePicker :from="dateFrom" :to="dateTo" @apply="handleDateRangeApply" />

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
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <SegmentedTimeSeriesChart
          :title="t('admin.registrations.by_platform')"
          icon="pi-mobile"
          :segments="PLATFORM_SEGMENTS"
          :series-data="dashboardStore.timeSeries.registrations_by_platform"
          :all-dates="allDates"
        />
        <SegmentedTimeSeriesChart
          :title="t('admin.registrations.by_method')"
          icon="pi-key"
          :segments="METHOD_SEGMENTS"
          :series-data="dashboardStore.timeSeries.registrations_by_method"
          :all-dates="allDates"
        />
        <SegmentedTimeSeriesChart
          :title="t('admin.registrations.by_source')"
          icon="pi-megaphone"
          :segments="sourceChart.segments"
          :series-data="sourceChart.seriesData"
          :all-dates="allDates"
        />
        <Card class="min-w-0">
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-flag text-primary" />
              <span>{{ t('admin.registration_campaigns.title') }}</span>
            </div>
          </template>
          <template #content>
            <DataTable
              v-if="metrics.registration_campaigns.length > 0"
              :value="metrics.registration_campaigns"
              size="small"
              stripedRows
            >
              <Column field="campaign" :header="t('admin.registration_campaigns.campaign')">
                <template #body="{ data }"><span class="break-all">{{ data.campaign ?? '-' }}</span></template>
              </Column>
              <Column field="source" :header="t('admin.registration_campaigns.source')">
                <template #body="{ data }"><span class="break-all">{{ data.source ?? '-' }}</span></template>
              </Column>
              <Column field="medium" :header="t('admin.registration_campaigns.medium')">
                <template #body="{ data }"><span class="break-all">{{ data.medium ?? '-' }}</span></template>
              </Column>
              <Column field="count" :header="t('admin.registration_campaigns.count')" />
            </DataTable>
            <div v-else class="text-surface-400 text-center py-4">
              {{ t('admin.registration_campaigns.empty') }}
            </div>
          </template>
        </Card>
      </div>

      <!-- The platforms members use (#1370) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <SegmentedTimeSeriesChart
          :title="t('admin.platform_activity.daily')"
          icon="pi-users"
          :segments="PLATFORM_SEGMENTS"
          :series-data="dailyActiveMembers"
          :all-dates="allDates"
          :stacked="false"
          :segment-totals="platformActivity?.active_members_by_platform"
        />
        <Card class="min-w-0">
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-chart-pie text-primary" />
              <span>{{ t('admin.platform_activity.period') }}</span>
            </div>
          </template>
          <template #content>
            <div v-if="dashboardStore.isLoadingPlatformActivity" class="flex justify-center py-8">
              <ProgressSpinner style="width: 40px; height: 40px" />
            </div>
            <div v-else-if="platformActivity" class="space-y-4">
              <div class="flex items-center justify-between gap-2">
                <span class="text-surface-600 dark:text-surface-400">{{ t('admin.platform_activity.multi_platform') }}</span>
                <span class="text-2xl font-bold">{{ platformActivity.multi_platform_members }}</span>
              </div>
              <div>
                <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-2">
                  {{ t('admin.platform_activity.by_registration_platform') }}
                </h3>
                <ul class="space-y-1">
                  <li v-for="segment in PLATFORM_SEGMENTS" :key="segment.value" class="flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2 min-w-0">
                      <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: segment.color }" />
                      <span class="truncate">{{ t(segment.labelKey) }}</span>
                    </span>
                    <span class="font-semibold">{{ platformActivity.active_members_by_registration_platform[segment.value] }}</span>
                  </li>
                </ul>
              </div>
            </div>
            <div v-else class="text-surface-400 text-center py-8">
              {{ t('admin.time_series.no_data') }}
            </div>
          </template>
        </Card>
        <Card class="min-w-0 md:col-span-2">
          <template #title>
            <div class="flex items-center gap-2 text-base">
              <i class="pi pi-mobile text-primary" />
              <span>{{ t('admin.platform_activity.app_versions') }}</span>
            </div>
          </template>
          <template #content>
            <DataTable
              v-if="platformActivity?.app_versions.length > 0"
              :value="platformActivity.app_versions"
              size="small"
              stripedRows
            >
              <Column field="platform" :header="t('admin.platform_activity.platform')">
                <template #body="{ data }">{{ platformLabel(data.platform) }}</template>
              </Column>
              <Column field="version" :header="t('admin.platform_activity.version')">
                <template #body="{ data }">{{ data.version ?? t('admin.platform_activity.no_version') }}</template>
              </Column>
              <Column field="members" :header="t('admin.platform_activity.members')" />
            </DataTable>
            <div v-else class="text-surface-400 text-center py-4">
              {{ t('admin.platform_activity.no_app_activity') }}
            </div>
          </template>
        </Card>
      </div>

      <!-- Engagement & Retention Section -->
      <Panel header="Engagement & rétention" toggleable>
        <template #icons>
          <i class="pi pi-chart-line text-primary" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                  <span v-if="generalMetrics?.dau_mau_ratio !== null && generalMetrics?.dau_mau_ratio !== undefined" class="text-2xl font-bold text-primary">
                    {{ generalMetrics.dau_mau_ratio }}%
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
                  <span v-if="generalMetrics?.retention7_days !== null && generalMetrics?.retention7_days !== undefined" :class="getRetentionClass(generalMetrics.retention7_days)" class="text-2xl font-bold">
                    {{ generalMetrics.retention7_days }}%
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
                  <span v-if="generalMetrics?.retention30_days !== null && generalMetrics?.retention30_days !== undefined" :class="getRetentionClass(generalMetrics.retention30_days)" class="text-2xl font-bold">
                    {{ generalMetrics.retention30_days }}%
                  </span>
                  <span v-else class="text-surface-400">-</span>
                </div>
              </div>
            </template>
          </Card>

          <Card class="lg:col-span-2">
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
        </div>
      </Panel>

      <!-- Spam/Abuse Detection Section -->
      <Panel header="Détection spam / abus" toggleable>
        <template #icons>
          <i class="pi pi-shield text-red-500" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Empty Accounts -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-user-minus text-orange-500" />
                <span>Comptes vides</span>
              </div>
            </template>
            <template #subtitle>
              Comptes créés sans avatar ni bio
            </template>
            <template #content>
              <DataTable
                v-if="metrics.recent_empty_accounts.length > 0"
                :value="metrics.recent_empty_accounts"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur">
                  <template #body="{ data }">
                    <RouterLink :to="{ name: 'app_user_public_profile', params: { username: data.username } }" class="text-primary hover:underline">{{ data.username }}</RouterLink>
                  </template>
                </Column>
                <Column field="email" header="Email" class="hidden md:table-cell" />
                <Column field="registration_date" header="Inscription" />
                <Column field="profile_completion_percent" header="Profil">
                  <template #body="{ data }">
                    <ProgressBar :value="data.profile_completion_percent" :showValue="true" style="height: 6px; width: 60px" />
                  </template>
                </Column>
              </DataTable>
              <div v-else class="text-surface-400 text-center py-4">
                Aucun compte vide détecté
              </div>
            </template>
          </Card>

          <!-- Message Spam Ratio -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-envelope text-red-500" />
                <span>Ratio spam messages</span>
                <ComingSoonBadge />
              </div>
            </template>
            <template #subtitle>
              Utilisateurs avec beaucoup de messages envoyés mais peu reçus
            </template>
            <template #content>
              <div class="text-surface-400 text-center py-4">
                Analyse des ratios de messages non disponible
              </div>
            </template>
          </Card>
        </div>
      </Panel>

      <!-- Suspicious Engagement Section -->
      <Panel header="Engagement suspect" toggleable collapsed>
        <template #icons>
          <i class="pi pi-eye text-yellow-500" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- External Link Posters -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-external-link text-yellow-500" />
                <span>Posteurs de liens externes</span>
                <ComingSoonBadge />
              </div>
            </template>
            <template #content>
              <div class="text-surface-400 text-center py-4">
                Analyse des liens externes non disponible
              </div>
            </template>
          </Card>

          <!-- Abnormal Activity Spikes -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-bolt text-yellow-500" />
                <span>Pics d'activité anormaux</span>
                <ComingSoonBadge />
              </div>
            </template>
            <template #content>
              <div class="text-surface-400 text-center py-4">
                Analyse des pics d'activité non disponible
              </div>
            </template>
          </Card>
        </div>
      </Panel>

      <!-- Community Health Section -->
      <Panel header="Santé de la communauté" toggleable>
        <template #icons>
          <i class="pi pi-heart text-green-500" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Profile Completion Rates -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-id-card text-primary" />
                <span
                  v-tooltip.top="'Score basé sur : avatar, bio, nom affiché, localisation, photo de couverture. Vide = 0-20%, Basique = 21-79%, Complet = 80-100%'"
                  class="cursor-help border-b border-dashed border-surface-400"
                >
                  Taux de complétion des profils
                </span>
              </div>
            </template>
            <template #content>
              <div>
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm text-surface-500">{{ metrics.profile_completion_rates.total }} utilisateurs inscrits</span>
                </div>
                <div class="flex items-center gap-4 mb-2">
                  <span class="text-2xl font-bold text-primary">{{ metrics.profile_completion_rates.avg_percent }}%</span>
                  <span class="text-sm text-surface-500">moyenne</span>
                </div>
                <div class="flex gap-2 flex-wrap">
                  <Tag v-tooltip.top="'0-20% de complétion'" severity="danger" :value="`Vide: ${metrics.profile_completion_rates.levels.empty}`" class="cursor-help" />
                  <Tag v-tooltip.top="'21-79% de complétion'" severity="warn" :value="`Basique: ${metrics.profile_completion_rates.levels.basic}`" class="cursor-help" />
                  <Tag v-tooltip.top="'80-100% de complétion'" severity="success" :value="`Complet: ${metrics.profile_completion_rates.levels.complete}`" class="cursor-help" />
                </div>
              </div>
            </template>
          </Card>

          <!-- Emails Sent -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-envelope text-primary" />
                <span>Emails envoyés</span>
              </div>
            </template>
            <template #content>
              <div v-if="metrics.emails_sent_by_type && Object.keys(metrics.emails_sent_by_type).length > 0" class="space-y-3">
                <div
                  v-for="(count, type) in metrics.emails_sent_by_type"
                  :key="type"
                  class="flex items-center justify-between"
                >
                  <span class="text-surface-600 dark:text-surface-400">{{ type }}</span>
                  <Badge :value="count" severity="secondary" />
                </div>
              </div>
              <div v-else class="text-surface-400 text-center py-4">
                Aucun email envoyé sur cette période
              </div>
            </template>
          </Card>

          <!-- Top Contributors -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-star text-yellow-500" />
                <span>Top contributeurs</span>
                <ComingSoonBadge />
              </div>
            </template>
            <template #content>
              <div class="text-surface-400 text-center py-4">
                Suivi des contributions non disponible
              </div>
            </template>
          </Card>
        </div>
      </Panel>

      <!-- Recent Activity Section -->
      <Panel header="Activité récente" toggleable>
        <template #icons>
          <i class="pi pi-clock text-blue-500" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Recent Registrations -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-user-plus text-blue-500" />
                <span>Inscriptions</span>
              </div>
            </template>
            <template #content>
              <DataTable
                v-if="metrics.recent_registrations.length > 0"
                :value="metrics.recent_registrations"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur">
                  <template #body="{ data }">
                    <RouterLink :to="{ name: 'app_user_public_profile', params: { username: data.username } }" class="text-primary hover:underline">{{ data.username }}</RouterLink>
                  </template>
                </Column>
                <Column field="registration_date" header="Date" />
                <Column field="profile_completion_percent" header="Profil">
                  <template #body="{ data }">
                    <ProgressBar :value="data.profile_completion_percent" :showValue="true" style="height: 6px; width: 60px" />
                  </template>
                </Column>
              </DataTable>
              <div v-else class="text-surface-400 text-center py-4">
                Aucune inscription sur cette période
              </div>
            </template>
          </Card>

          <!-- Top Messagers -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-comments text-green-500" />
                <span>Top messagers</span>
              </div>
            </template>
            <template #content>
              <DataTable
                v-if="metrics.top_messagers.length > 0"
                :value="metrics.top_messagers"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur">
                  <template #body="{ data }">
                    <RouterLink :to="{ name: 'app_user_public_profile', params: { username: data.username } }" class="text-primary hover:underline">{{ data.username }}</RouterLink>
                  </template>
                </Column>
                <Column field="message_count" header="Messages" />
                <Column field="account_age_days" header="Âge compte">
                  <template #body="{ data }">
                    {{ data.account_age_days }}j
                  </template>
                </Column>
                <Column field="avg_messages_per_day">
                  <template #header>
                    <span
                      v-tooltip.top="'Moyenne de messages par jour sur la période sélectionnée'"
                      class="cursor-help border-b border-dashed border-surface-400"
                    >
                      Moy/jour
                    </span>
                  </template>
                  <template #body="{ data }">
                    {{ data.avg_messages_per_day }}
                  </template>
                </Column>
              </DataTable>
              <div v-else class="text-surface-400 text-center py-4">
                Aucun message sur cette période
              </div>
            </template>
          </Card>

          <!-- Recent Teachers -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-graduation-cap text-purple-500" />
                <span>Derniers professeurs inscrits</span>
              </div>
            </template>
            <template #content>
              <DataTable
                v-if="metrics.recent_teachers.length > 0"
                :value="metrics.recent_teachers"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur">
                  <template #body="{ data }">
                    <RouterLink :to="{ name: 'app_user_public_profile', params: { username: data.username } }" class="text-primary hover:underline">{{ data.username }}</RouterLink>
                  </template>
                </Column>
                <Column field="registration_date" header="Date" />
              </DataTable>
              <div v-else class="text-surface-400 text-center py-4">
                Aucun professeur inscrit sur cette période
              </div>
            </template>
          </Card>
        </div>
      </Panel>
    </template>
  </div>
</template>

<script setup>
import { eachDayOfInterval, format, subDays } from 'date-fns'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ComingSoonBadge from '../../components/Admin/ComingSoonBadge.vue'
import DateRangePicker from '../../components/Admin/DateRangePicker.vue'
import SegmentedTimeSeriesChart from '../../components/Admin/SegmentedTimeSeriesChart.vue'
import TimeSeriesChart from '../../components/Admin/TimeSeriesChart.vue'
import UserSearchPanel from '../../components/Admin/UserSearchPanel.vue'
import { useAdminDashboardStore } from '../../store/admin/dashboard.js'
import { cappedSegments } from '../../utils/segmentedTimeSeries.js'

// The values the server records (#1355, #1370), each with its literal key so the catalogue test sees it used.
const PLATFORM_SEGMENTS = [
  { value: 'web', labelKey: 'admin.registration_platform.web', color: '#6366f1' },
  { value: 'android', labelKey: 'admin.registration_platform.android', color: '#22c55e' },
  { value: 'ios', labelKey: 'admin.registration_platform.ios', color: '#0ea5e9' },
  { value: 'unknown_app', labelKey: 'admin.registration_platform.unknown_app', color: '#94a3b8' }
]
const METHOD_SEGMENTS = [
  { value: 'password', labelKey: 'admin.registration_method.password', color: '#6366f1' },
  { value: 'google', labelKey: 'admin.registration_method.google', color: '#f59e0b' },
  { value: 'apple', labelKey: 'admin.registration_method.apple', color: '#64748b' }
]
// A source is a utm_source or a referring site, so there can be hundreds: the rest is one « Autres » bar (#1369).
const SOURCE_SEGMENT_LIMIT = 6
// What UserRepository::countRegistrationsByDateAndSource() names a web sign-up with neither.
const DIRECT_SOURCE = '(direct)'
const OTHER_SOURCES = '(other)'
const SOURCE_COLORS = ['#6366f1', '#22c55e', '#f59e0b', '#0ea5e9', '#ec4899', '#14b8a6']

const { t } = useI18n()
const dashboardStore = useAdminDashboardStore()

const metrics = computed(() => dashboardStore.userMetrics)
const generalMetrics = computed(() => dashboardStore.generalMetrics)
const platformActivity = computed(() => dashboardStore.platformActivity)

// The daily points with the period's distinct total: a member active on several days or platforms counts once.
const dailyActiveMembers = computed(() => ({
  data: platformActivity.value && {
    data_points: platformActivity.value.daily_active_members,
    total: platformActivity.value.active_members
  },
  isLoading: dashboardStore.isLoadingPlatformActivity,
  error: null
}))

function platformLabel(value) {
  const segment = PLATFORM_SEGMENTS.find((platform) => platform.value === value)
  return segment ? t(segment.labelKey) : value
}

const sourceChart = computed(() => {
  const series = dashboardStore.timeSeries.registrations_by_source
  if (!series?.data) return { seriesData: series, segments: [] }

  const { segments, dataPoints } = cappedSegments(
    series.data.data_points,
    SOURCE_SEGMENT_LIMIT,
    OTHER_SOURCES
  )
  return {
    seriesData: { ...series, data: { ...series.data, data_points: dataPoints } },
    segments: segments.map(sourceSegment)
  }
})

function sourceSegment(value, index) {
  if (value === DIRECT_SOURCE) {
    return { value, labelKey: 'admin.registration_source.direct', color: '#94a3b8' }
  }
  if (value === OTHER_SOURCES) {
    return { value, labelKey: 'admin.registration_source.other', color: '#cbd5e1' }
  }
  return { value, label: value, color: SOURCE_COLORS[index % SOURCE_COLORS.length] }
}

const today = new Date()
const dateFrom = ref(subDays(today, 30))
const dateTo = ref(today)

const allDates = computed(() =>
  eachDayOfInterval({ start: dateFrom.value, end: dateTo.value }).map((d) =>
    format(d, 'yyyy-MM-dd')
  )
)

function formatDate(date) {
  return format(date, 'yyyy-MM-dd')
}

function loadDateFilteredData() {
  const from = formatDate(dateFrom.value)
  const to = formatDate(dateTo.value)
  dashboardStore.loadUserMetrics(from, to)
  dashboardStore.loadPlatformActivity(from, to)
  const metricNames = [
    'registrations',
    'logins',
    'messages',
    'registrations_by_platform',
    'registrations_by_method',
    'registrations_by_source'
  ]
  for (const metric of metricNames) {
    dashboardStore.loadTimeSeries(metric, from, to)
  }
}

function getRetentionClass(value) {
  if (value >= 50) return 'text-green-500'
  if (value >= 25) return 'text-yellow-500'
  return 'text-red-500'
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  loadDateFilteredData()
}

function refreshData() {
  loadDateFilteredData()
}

onMounted(() => {
  dashboardStore.loadGeneralMetrics()
  loadDateFilteredData()
})
</script>
