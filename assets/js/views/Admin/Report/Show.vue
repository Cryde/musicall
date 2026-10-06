<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center gap-3">
      <Button
        icon="pi pi-arrow-left"
        severity="secondary"
        text
        rounded
        aria-label="Retour aux signalements"
        @click="router.push({ name: 'admin_reports_index' })"
      />
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-100">Signalement</h1>
      <template v-if="report">
        <Tag :value="reportTargetTypeLabel(report.target_type)" severity="secondary" />
        <Tag v-if="isPending" value="En attente" severity="warn" />
        <Tag v-else :value="reportOutcomeLabel(report.outcome) ?? 'Traité'" severity="success" />
      </template>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <ProgressSpinner style="width: 50px; height: 50px" />
    </div>

    <Message v-else-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

    <template v-else-if="report">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Panel header="Contenu signalé" class="lg:col-span-2">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <Tag v-if="liveState" :value="liveState.label" :severity="liveState.severity" />
              <RouterLink
                v-if="contentLink.to"
                :to="contentLink.to"
                target="_blank"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
              >
                {{ contentLink.text }}
                <i class="pi pi-external-link text-xs" aria-hidden="true" />
              </RouterLink>
              <span v-else class="text-sm text-surface-600 dark:text-surface-400">{{ contentLink.text }}</span>
            </div>
            <p class="text-xs text-surface-600 dark:text-surface-400">
              Tel qu'il était au moment du signalement :
            </p>
            <blockquote
              class="whitespace-pre-line break-words rounded-lg bg-surface-100 dark:bg-surface-800 p-4 text-sm"
            >
              {{ report.snapshot_text || '(sans texte)' }}
            </blockquote>
          </div>
        </Panel>

        <Panel header="Signalement">
          <dl class="flex flex-col gap-2 text-sm">
            <div v-for="row in reportRows" :key="row.label" class="flex justify-between gap-4">
              <dt class="text-surface-600 dark:text-surface-400">{{ row.label }}</dt>
              <dd class="text-right">{{ row.value }}</dd>
            </div>
          </dl>
          <div v-if="report.details" class="mt-4">
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Précisions</p>
            <p class="whitespace-pre-line break-words text-sm">{{ report.details }}</p>
          </div>
        </Panel>

        <Panel header="Auteur" class="lg:col-span-3">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div v-if="report.target_author" class="flex items-center gap-2">
              <RouterLink
                :to="{ name: 'admin_users_show', params: { id: report.target_author.id } }"
                class="font-medium text-primary hover:underline"
              >
                {{ report.target_author.username }}
              </RouterLink>
              <Tag v-if="report.target_author.is_suspended" value="suspendu" severity="danger" />
            </div>
            <span v-else class="text-sm text-surface-600 dark:text-surface-400">
              Ce contenu n'a pas d'auteur identifié.
            </span>

            <div v-if="isPending" class="flex flex-wrap gap-2">
              <Button
                label="Classer sans suite"
                icon="pi pi-check"
                severity="secondary"
                outlined
                :loading="isDismissing"
                @click="confirmDismiss"
              />
              <Button
                label="Suspendre l'auteur"
                icon="pi pi-ban"
                severity="danger"
                :disabled="!canSuspendAuthor"
                @click="showSuspendDialog = true"
              />
            </div>
          </div>
        </Panel>
      </div>

      <SuspendAccountDialog
        v-if="report.target_author"
        v-model:visible="showSuspendDialog"
        :username="report.target_author.username"
        :submit="(reason) => adminReportApi.suspendAuthor(report.id, reason)"
        @suspended="handleActionDone('Compte suspendu')"
      />
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import adminReportApi from '../../../api/admin/report.js'
import SuspendAccountDialog from '../../../components/Admin/SuspendAccountDialog.vue'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { formatDate } from '../../../utils/date.js'
import {
  reportContentLink,
  reportLiveState,
  reportOutcomeLabel,
  reportReasonLabel,
  reportTargetTypeLabel
} from '../../../utils/reportTarget.js'

useTitle('Signalement - Admin - MusicAll')

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const notificationStore = useNotificationStore()

const report = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')
const isDismissing = ref(false)
const showSuspendDialog = ref(false)

const isPending = computed(() => !report.value?.resolution_datetime)
const liveState = computed(() => reportLiveState(report.value?.live_state))
const contentLink = computed(() => reportContentLink(report.value))
const canSuspendAuthor = computed(
  () =>
    !!report.value?.target_author &&
    !report.value.target_author.is_suspended &&
    !report.value.target_author.is_admin
)

const reportRows = computed(() => {
  const rows = [
    { label: 'Motif', value: reportReasonLabel(report.value.reason) },
    { label: 'Signalé par', value: report.value.reporter.username },
    { label: 'Reçu le', value: formatDate(report.value.creation_datetime) },
    { label: 'En attente sur ce contenu', value: report.value.pending_report_count }
  ]
  if (report.value.resolution_datetime) {
    rows.push({ label: 'Traité le', value: formatDate(report.value.resolution_datetime) })
    rows.push({ label: 'Traité par', value: report.value.resolved_by_username ?? '-' })
  }

  return rows
})

async function load() {
  try {
    report.value = await adminReportApi.get(route.params.id)
    errorMessage.value = ''
  } catch (e) {
    errorMessage.value =
      e?.response?.status === 404
        ? 'Signalement introuvable.'
        : e?.response?.data?.detail || 'Impossible de charger ce signalement.'
  } finally {
    isLoading.value = false
  }
}

function confirmDismiss() {
  confirm.require({
    header: 'Classer sans suite',
    message:
      'Classer ce signalement sans suite ? Tous les signalements en attente sur ce contenu seront clos.',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Classer sans suite',
    accept: dismiss
  })
}

async function dismiss() {
  isDismissing.value = true
  try {
    await adminReportApi.dismiss(report.value.id)
    await handleActionDone('Signalement classé sans suite')
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isDismissing.value = false
  }
}

async function handleActionDone(summary) {
  toast.add({ severity: 'success', summary, life: 3000 })
  // The pending badges count reports, and this action just closed some.
  await Promise.all([load(), notificationStore.loadNotifications()])
}

onMounted(load)
</script>
