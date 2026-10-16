<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="flex justify-center py-12">
      <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 max-w-md w-full text-center">
        <ProgressSpinner v-if="isProcessing" class="mb-4" />

        <template v-if="isLoading">
          <ProgressSpinner class="mb-4" />
        </template>

        <template v-else-if="!isProcessing && !error && !result && invitationInfo">
          <i class="pi pi-envelope text-4xl text-primary mb-4"></i>
          <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100 mb-2">
            {{ t('band_space.invitation.title', { name: invitationInfo.band_space_name }) }}
          </h2>
          <p class="text-surface-500 dark:text-surface-400 mb-6">
            {{ t('band_space.invitation.question') }}
          </p>
          <div class="flex gap-3 justify-center">
            <Button :label="t('band_space.invitation.decline')" severity="secondary" outlined @click="handleDecline" />
            <Button :label="t('band_space.invitation.accept')" @click="handleAccept" />
          </div>
        </template>

        <template v-if="result === 'declined'">
          <i class="pi pi-times-circle text-4xl text-surface-400 mb-4"></i>
          <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100 mb-2">
            {{ t('band_space.invitation.declined_title') }}
          </h2>
          <p class="text-surface-500 dark:text-surface-400 mb-6">
            {{ t('band_space.invitation.declined_text') }}
          </p>
        </template>

        <template v-if="error">
          <i class="pi pi-exclamation-circle text-4xl text-red-500 mb-4"></i>
          <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100 mb-2">
            {{ t('band_space.common.error') }}
          </h2>
          <p class="text-red-500 mb-6">{{ error }}</p>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const route = useRoute()
const router = useRouter()
const bandSpaceStore = useBandSpaceStore()
const { t } = useI18n()
const token = route.params.token

const isLoading = ref(true)
const isProcessing = ref(false)
const result = ref(null)
const error = ref(null)
const invitationInfo = ref(null)

onMounted(async () => {
  try {
    invitationInfo.value = await bandSpaceSettingsApi.getInvitationInfo(token)
  } catch (e) {
    error.value = e.message
  } finally {
    isLoading.value = false
  }
})

async function handleAccept() {
  isProcessing.value = true
  error.value = null
  try {
    const data = await bandSpaceSettingsApi.acceptInvitation(token)
    await bandSpaceStore.loadMyBandSpaces()
    router.replace({ name: BAND_SPACE_ROUTES.DASHBOARD, params: { id: data.band_space_id } })
  } catch (e) {
    error.value = e.message
  } finally {
    isProcessing.value = false
  }
}

async function handleDecline() {
  isProcessing.value = true
  error.value = null
  try {
    await bandSpaceSettingsApi.declineInvitation(token)
    result.value = 'declined'
  } catch (e) {
    error.value = e.message
  } finally {
    isProcessing.value = false
  }
}
</script>
