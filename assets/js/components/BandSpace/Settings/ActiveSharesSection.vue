<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100">{{ t('band_space.settings.section.shares') }}</h2>
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
      {{ t('band_space.shares.empty') }}
    </p>

    <DataTable v-else :value="filesStore.shares" data-key="id" class="text-sm">
      <Column field="file_original_name" :header="t('band_space.shares.file')">
        <template #body="{ data }">
          <div class="flex items-center gap-2 min-w-0">
            <i class="pi pi-file text-surface-400"></i>
            <span class="truncate">{{ data.file_original_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="expiry_datetime" :header="t('band_space.shares.expires')" headerStyle="width:12rem">
        <template #body="{ data }">
          <span class="tabular-nums">{{ formatDate(data.expiry_datetime) }}</span>
        </template>
      </Column>

      <Column field="access_count" :header="t('band_space.shares.access_count')" headerStyle="width:6rem">
        <template #body="{ data }">
          <span class="tabular-nums">{{ data.access_count }}</span>
        </template>
      </Column>

      <Column field="last_access_datetime" :header="t('band_space.shares.last_access')" headerStyle="width:12rem">
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

      <Column v-if="isAdmin" header="" headerStyle="width:5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            v-tooltip.top="t('band_space.shares.revoke_tooltip')"
            :aria-label="t('band_space.shares.revoke_aria')"
            @click="confirmRevoke(data)"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { dateFnsLocale, intlLocale } from '../../../i18n/formatLocale.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const route = useRoute()
const filesStore = useBandFilesStore()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
// Every member may read the list, only an admin may revoke a link, so the column is dropped rather
// than offering a button the server would refuse.
const { isAdmin } = useBandSpaceNavigation()
// Wipe previous space's files-store state synchronously before first render
// so switching from A's Settings/Shares to B's doesn't flash A's shares.
filesStore.clear()

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
    message: t('band_space.shares.revoke_message', { name: share.file_original_name }),
    header: t('band_space.shares.revoke_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space.shares.revoke'),
    rejectLabel: t('band_space.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await filesStore.revokeShare(bandSpaceId, share.id)
        toast.add({ severity: 'success', summary: t('band_space.shares.revoked'), life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(intlLocale(), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatRelative(iso) {
  if (!iso) return ''
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: dateFnsLocale() })
}
</script>
