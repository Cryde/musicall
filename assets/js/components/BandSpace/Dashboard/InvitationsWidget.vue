<template>
  <DashboardWidget
    title="Invitations en attente"
    icon="pi pi-envelope"
    :is-loading="isLoading"
    :error="error"
    :is-empty="!isLoading && !error && pendingCount === 0"
    empty-message="Aucune invitation en attente."
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_parameters', params: { id: bandSpaceId } }"
        class="text-xs text-primary hover:underline"
      >
        Gérer
      </RouterLink>
    </template>

    <div class="flex items-center justify-between">
      <div>
        <div class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ pendingCount }}</div>
        <div class="text-xs text-surface-500 mt-1">
          {{ pendingCount === 1 ? 'invitation en attente' : 'invitations en attente' }}
        </div>
      </div>
      <i class="pi pi-clock text-3xl text-amber-500" aria-hidden="true" />
    </div>
  </DashboardWidget>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceSettingsApi from '../../../api/bandSpace/band-space-settings.js'
import DashboardWidget from './DashboardWidget.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const invitations = ref([])
const isLoading = ref(true)
const error = ref(null)

const pendingCount = computed(
  () => invitations.value.filter((inv) => inv.status === 'pending').length
)

onMounted(async () => {
  try {
    invitations.value = await bandSpaceSettingsApi.getInvitations(props.bandSpaceId)
  } catch {
    error.value = 'Invitations indisponibles.'
  } finally {
    isLoading.value = false
  }
})
</script>
