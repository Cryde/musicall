<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6 max-md:px-0 max-md:pb-1">
    <div class="flex items-center justify-between mb-4 max-md:mb-1 max-md:pr-2 max-md:pl-4">
      <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100">{{ t('band_space.settings.section.shares') }}</h2>
      <Button
        icon="pi pi-refresh"
        size="small"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space.settings.phone.refresh')"
        :loading="filesStore.isLoadingShares"
        @click="loadShares"
      />
    </div>

    <div v-if="filesStore.isLoadingShares && filesStore.shares.length === 0" class="flex flex-col gap-2 max-md:px-4">
      <Skeleton v-for="i in 3" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
    </div>

    <p
      v-else-if="filesStore.shares.length === 0"
      class="text-sm italic text-surface-400 text-center py-8 max-md:px-4"
    >
      {{ t('band_space.shares.empty') }}
    </p>

    <!-- A phone has no room for six columns: one row per link, its facts stacked. -->
    <ul v-else-if="isBelowMd" class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li v-for="share in filesStore.shares" :key="share.id" class="flex items-start gap-3 py-2.5 pr-1 pl-4">
        <span
          class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
          aria-hidden="true"
        >
          <i class="pi pi-link" />
        </span>
        <div class="min-w-0 flex-1 text-xs text-surface-600 dark:text-surface-300">
          <p class="m-0 flex items-center gap-1.5 text-sm font-medium text-surface-800 dark:text-surface-100">
            <span class="truncate">{{ share.file_original_name }}</span>
            <i
              v-if="share.has_password"
              class="pi pi-lock shrink-0 text-xs text-amber-600"
              role="img"
              :aria-label="t('band_space.settings.phone.password_protected')"
            />
          </p>
          <p class="m-0">{{ t('band_space.members.expires_on', { date: formatDate(share.expiry_datetime) }) }}</p>
          <p class="m-0">{{ accessLine(share) }}</p>
        </div>
        <Button
          v-if="isAdmin"
          icon="pi pi-trash"
          severity="danger"
          text
          rounded
          class="h-11! w-11! shrink-0"
          :aria-label="t('band_space.shares.revoke_aria')"
          @click="confirmRevoke(share)"
        />
      </li>
    </ul>

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
          <span v-else class="text-surface-400">{{ noValue() }}</span>
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
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { dateFnsLocale, intlLocale, noValue } from '../../../i18n/formatLocale.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const route = useRoute()
const filesStore = useBandFilesStore()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
// Every member may read the list, only an admin may revoke a link, so the column is dropped rather
// than offering a button the server would refuse.
const { isAdmin } = useBandSpaceNavigation()
const isBelowMd = useIsBelowMd()
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
  if (!iso) return noValue()
  return new Date(iso).toLocaleDateString(intlLocale(), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// « 4 accès · dernier il y a 2 jours », or « 0 accès · jamais ouvert ».
function accessLine(share) {
  const count = t('band_space.settings.phone.share_access_count', { count: share.access_count })
  const last = share.last_access_datetime
    ? t('band_space.settings.phone.share_last_access', {
        when: formatRelative(share.last_access_datetime)
      })
    : t('band_space.settings.phone.share_never_opened')

  return `${count} · ${last}`
}

function formatRelative(iso) {
  if (!iso) return ''
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: dateFnsLocale() })
}
</script>
