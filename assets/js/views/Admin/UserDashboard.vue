<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <RouterLink :to="{ name: 'admin_dashboard' }" class="text-surface-500 hover:text-primary transition-colors">
          <i class="pi pi-arrow-left" />
        </RouterLink>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Gestion des utilisateurs</h1>
      </div>
      <Button
        icon="pi pi-refresh"
        severity="secondary"
        text
        rounded
        :loading="dashboardStore.isLoadingUsers"
        @click="refreshData"
      />
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card>
        <template #content>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="pi pi-user-plus text-blue-600 dark:text-blue-400 text-xl" />
            </div>
            <div>
              <p class="text-sm text-surface-500">Inscriptions 24h</p>
              <p class="text-2xl font-bold">{{ metrics?.total_users_last24h ?? '-' }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-users text-green-600 dark:text-green-400 text-xl" />
            </div>
            <div>
              <p class="text-sm text-surface-500">Inscriptions 7 jours</p>
              <p class="text-2xl font-bold">{{ metrics?.total_users_last7_days ?? '-' }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
              <i class="pi pi-exclamation-triangle text-orange-600 dark:text-orange-400 text-xl" />
            </div>
            <div>
              <p class="text-sm text-surface-500">Comptes non confirmés</p>
              <p class="text-2xl font-bold">{{ metrics?.unconfirmed_accounts ?? '-' }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

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
      <!-- Spam/Abuse Detection Section -->
      <Panel header="Détection spam / abus" toggleable>
        <template #icons>
          <i class="pi pi-shield text-red-500" />
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Recent Empty Accounts -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-user-minus text-orange-500" />
                <span>Comptes vides récents (24h)</span>
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
                <Column field="username" header="Utilisateur" />
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
                  v-tooltip.top="'Score basé sur : avatar, bio, localisation, profil musicien. Vide = 0-20%, Basique = 21-70%, Complet = 71-100%'"
                  class="cursor-help border-b border-dashed border-surface-400"
                >
                  Taux de complétion des profils
                </span>
              </div>
            </template>
            <template #content>
              <div class="space-y-6">
                <!-- Last 7 days -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <span class="font-medium">7 derniers jours</span>
                    <span class="text-sm text-surface-500">{{ metrics.profile_completion_rates.last_7_days.total }} utilisateurs</span>
                  </div>
                  <div class="flex items-center gap-4 mb-2">
                    <span class="text-2xl font-bold text-primary">{{ metrics.profile_completion_rates.last_7_days.avg_percent }}%</span>
                    <span class="text-sm text-surface-500">moyenne</span>
                  </div>
                  <div class="flex gap-2 flex-wrap">
                    <Tag v-tooltip.top="'0-20% de complétion'" severity="danger" :value="`Vide: ${metrics.profile_completion_rates.last_7_days.levels.empty}`" class="cursor-help" />
                    <Tag v-tooltip.top="'21-70% de complétion'" severity="warn" :value="`Basique: ${metrics.profile_completion_rates.last_7_days.levels.basic}`" class="cursor-help" />
                    <Tag v-tooltip.top="'71-100% de complétion'" severity="success" :value="`Complet: ${metrics.profile_completion_rates.last_7_days.levels.complete}`" class="cursor-help" />
                  </div>
                </div>

                <Divider />

                <!-- Last 30 days -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <span class="font-medium">30 derniers jours</span>
                    <span class="text-sm text-surface-500">{{ metrics.profile_completion_rates.last_30_days.total }} utilisateurs</span>
                  </div>
                  <div class="flex items-center gap-4 mb-2">
                    <span class="text-2xl font-bold text-primary">{{ metrics.profile_completion_rates.last_30_days.avg_percent }}%</span>
                    <span class="text-sm text-surface-500">moyenne</span>
                  </div>
                  <div class="flex gap-2 flex-wrap">
                    <Tag v-tooltip.top="'0-20% de complétion'" severity="danger" :value="`Vide: ${metrics.profile_completion_rates.last_30_days.levels.empty}`" class="cursor-help" />
                    <Tag v-tooltip.top="'21-70% de complétion'" severity="warn" :value="`Basique: ${metrics.profile_completion_rates.last_30_days.levels.basic}`" class="cursor-help" />
                    <Tag v-tooltip.top="'71-100% de complétion'" severity="success" :value="`Complet: ${metrics.profile_completion_rates.last_30_days.levels.complete}`" class="cursor-help" />
                  </div>
                </div>
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
                <span>Dernières inscriptions</span>
              </div>
            </template>
            <template #content>
              <DataTable
                v-if="metrics.recent_registrations.length > 0"
                :value="metrics.recent_registrations"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur" />
                <Column field="registration_date" header="Date" />
                <Column field="profile_completion_percent" header="Profil">
                  <template #body="{ data }">
                    <ProgressBar :value="data.profile_completion_percent" :showValue="true" style="height: 6px; width: 60px" />
                  </template>
                </Column>
              </DataTable>
              <div v-else class="text-surface-400 text-center py-4">
                Aucune inscription récente
              </div>
            </template>
          </Card>

          <!-- Top Messagers -->
          <Card>
            <template #title>
              <div class="flex items-center gap-2 text-base">
                <i class="pi pi-comments text-green-500" />
                <span>Top messagers (7 jours)</span>
              </div>
            </template>
            <template #content>
              <DataTable
                v-if="metrics.top_messagers.length > 0"
                :value="metrics.top_messagers"
                size="small"
                stripedRows
              >
                <Column field="username" header="Utilisateur" />
                <Column field="message_count" header="Messages" />
                <Column field="account_age_days" header="Âge compte">
                  <template #body="{ data }">
                    {{ data.account_age_days }}j
                  </template>
                </Column>
                <Column field="avg_messages_per_day">
                  <template #header>
                    <span
                      v-tooltip.top="'Moyenne de messages par jour depuis la création du compte'"
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
                Aucun message cette semaine
              </div>
            </template>
          </Card>
        </div>
      </Panel>
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted } from 'vue'
import { useAdminDashboardStore } from '../../store/admin/dashboard.js'
import ComingSoonBadge from '../../components/Admin/ComingSoonBadge.vue'

const dashboardStore = useAdminDashboardStore()

const metrics = computed(() => dashboardStore.userMetrics)

async function refreshData() {
  await dashboardStore.loadUserMetrics()
}

onMounted(async () => {
  await refreshData()
})
</script>
