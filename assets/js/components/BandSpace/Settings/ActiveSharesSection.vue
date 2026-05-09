<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100">Partages actifs</h2>
      <Button
        icon="pi pi-refresh"
        size="small"
        text
        :loading="filesStore.isLoadingShares"
        @click="loadShares"
      />
    </div>

    <div v-if="filesStore.isLoadingShares && filesStore.shares.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 3" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
    </div>

    <p
      v-else-if="filesStore.shares.length === 0"
      class="text-sm italic text-surface-400 text-center py-8"
    >
      Aucun lien de partage actif pour ce Band Space.
    </p>

    <DataTable v-else :value="filesStore.shares" data-key="id" class="text-sm">
      <Column field="file_original_name" header="Fichier">
        <template #body="{ data }">
          <div class="flex items-center gap-2 min-w-0">
            <i class="pi pi-file text-surface-400"></i>
            <span class="truncate">{{ data.file_original_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="expiry_datetime" header="Expire le" headerStyle="width:12rem">
        <template #body="{ data }">
          <span class="tabular-nums">{{ formatDate(data.expiry_datetime) }}</span>
        </template>
      </Column>

      <Column field="access_count" header="Accès" headerStyle="width:6rem">
        <template #body="{ data }">
          <span class="tabular-nums">{{ data.access_count }}</span>
        </template>
      </Column>

      <Column field="last_access_datetime" header="Dernier accès" headerStyle="width:12rem">
        <template #body="{ data }">
          <span v-if="data.last_access_datetime" class="text-surface-500">
            {{ formatRelative(data.last_access_datetime) }}
          </span>
          <span v-else class="text-surface-400">—</span>
        </template>
      </Column>

      <Column field="has_password" header="" headerStyle="width:3rem">
        <template #body="{ data }">
          <i v-if="data.has_password" class="pi pi-lock text-amber-600"></i>
        </template>
      </Column>

      <Column header="" headerStyle="width:5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            @click="confirmRevoke(data)"
          />
        </template>
      </Column>
    </DataTable>
  </div>

  <ConfirmDialog />
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const route = useRoute()
const filesStore = useBandFilesStore()
const confirm = useConfirm()
const toast = useToast()

const bandSpaceId = route.params.id

onMounted(() => {
  loadShares()
})

function loadShares() {
  if (bandSpaceId) {
    filesStore.fetchShares(bandSpaceId)
  }
}

function confirmRevoke(share) {
  confirm.require({
    message: `Révoquer le lien de partage pour « ${share.file_original_name} » ?`,
    header: 'Confirmer la révocation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Révoquer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await filesStore.revokeShare(bandSpaceId, share.id)
      toast.add({
        severity: 'success',
        summary: 'Lien révoqué',
        life: 3000
      })
    }
  })
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatRelative(iso) {
  if (!iso) return ''
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: fr })
}
</script>
