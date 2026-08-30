<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Messages signalés
    </h1>

    <DataTable
      :value="adminForumReportStore.pendingReports"
      :loading="adminForumReportStore.isLoading"
      stripedRows
      tableStyle="min-width: 50rem"
    >
      <template #empty>
        <div class="text-center py-8 text-surface-500">
          Il n'y a aucun message signalé en attente de traitement.
        </div>
      </template>

      <Column header="Message signalé">
        <template #body="{ data }">
          <div class="flex flex-col gap-1">
            <span class="text-surface-800 dark:text-surface-100">{{ data.post_excerpt }}</span>
            <span class="text-xs text-surface-500">
              par {{ data.post_author_username }} dans « {{ data.topic_title }} »
            </span>
          </div>
        </template>
      </Column>

      <Column header="Motif">
        <template #body="{ data }">
          <span class="text-surface-700 dark:text-surface-200">{{ data.reason }}</span>
        </template>
      </Column>

      <Column header="Signalé par" style="width: 180px">
        <template #body="{ data }">
          <div class="flex flex-col gap-1">
            <span>{{ data.reporter_username }}</span>
            <span class="text-xs text-surface-500">{{ formatDate(data.creation_datetime) }}</span>
          </div>
        </template>
      </Column>

      <Column header="Actions" style="width: 130px">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              icon="pi pi-eye"
              severity="info"
              text
              rounded
              v-tooltip.top="'Voir le post'"
              aria-label="Voir le post"
              as="router-link"
              :to="postRoute(data)"
              target="_blank"
            />
            <Button
              icon="pi pi-check"
              severity="success"
              text
              rounded
              v-tooltip.top="'Marquer comme résolu'"
              aria-label="Marquer comme résolu"
              @click="confirmResolve(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted } from 'vue'
import { useAdminForumReportStore } from '../../../store/admin/forumReport.js'
import { formatDate } from '../../../utils/date.js'

const confirm = useConfirm()
const toast = useToast()
const adminForumReportStore = useAdminForumReportStore()

onMounted(async () => {
  await adminForumReportStore.loadPendingReports()
})

function postRoute(report) {
  return {
    name: 'forum_topic_item',
    params:
      report.topic_page === 1
        ? { slug: report.topic_slug }
        : { slug: report.topic_slug, page: report.topic_page },
    hash: `#post-${report.post_id}`
  }
}

function confirmResolve(report) {
  confirm.require({
    message: `Marquer le signalement de ${report.reporter_username} comme résolu ?`,
    header: 'Confirmation',
    icon: 'pi pi-check-circle',
    acceptLabel: 'Marquer comme résolu',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await adminForumReportStore.resolveReport(report.id)
        toast.add({
          severity: 'success',
          summary: 'Signalement résolu',
          detail: 'Le signalement a été retiré de la file de modération.',
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors du traitement du signalement.',
          life: 3000
        })
      }
    }
  })
}
</script>
