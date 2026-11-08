<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="flex justify-center py-12">
      <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 max-w-md w-full text-center">
        <ProgressSpinner v-if="isProcessing" class="mb-4" />

        <template v-if="isLoading">
          <ProgressSpinner class="mb-4" />
        </template>

        <template v-else-if="!isProcessing && !error && !refusal && !result && invitationInfo">
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

        <template v-if="refusal">
          <i :class="['pi text-4xl mb-4', refusal.icon]" aria-hidden="true"></i>
          <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100 mb-2">
            {{ t(refusal.titleKey) }}
          </h2>
          <p class="text-surface-500 dark:text-surface-400 mb-6">{{ t(refusal.textKey) }}</p>
          <Button
            v-if="refusal.actionKey"
            as="router-link"
            :to="{ name: BAND_SPACE_ROUTES.INDEX }"
            :label="t(refusal.actionKey)"
          />
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
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import {
  INVITATION_REFUSAL,
  invitationRefusalOnAnswer,
  invitationRefusalOnOpen
} from '../../utils/bandSpaceInvitation.js'

// Each refusal says what happened and what to do next, instead of the API's bare message (#1359).
const REFUSALS = {
  [INVITATION_REFUSAL.ALREADY_MEMBER]: {
    icon: 'pi-check-circle text-primary',
    titleKey: 'band_space.invitation.refusal.already_member_title',
    textKey: 'band_space.invitation.refusal.already_member_text',
    actionKey: 'band_space.invitation.refusal.already_member_action'
  },
  [INVITATION_REFUSAL.NO_LONGER_VALID]: {
    icon: 'pi-clock text-surface-400',
    titleKey: 'band_space.invitation.refusal.no_longer_valid_title',
    textKey: 'band_space.invitation.refusal.no_longer_valid_text'
  },
  [INVITATION_REFUSAL.OTHER_RECIPIENT]: {
    icon: 'pi-user text-surface-400',
    titleKey: 'band_space.invitation.refusal.other_recipient_title',
    textKey: 'band_space.invitation.refusal.other_recipient_text'
  }
}

const route = useRoute()
const router = useRouter()
const bandSpaceStore = useBandSpaceStore()
const { t } = useI18n()
const token = route.params.token

const isLoading = ref(true)
const isProcessing = ref(false)
const result = ref(null)
const error = ref(null)
const refusalReason = ref(null)
const refusal = computed(() => REFUSALS[refusalReason.value] ?? null)
const invitationInfo = ref(null)

function showFailure(failure, reasonFor) {
  refusalReason.value = reasonFor(failure.status)
  error.value = refusalReason.value ? null : failure.message
}

onMounted(async () => {
  try {
    invitationInfo.value = await bandSpaceSettingsApi.getInvitationInfo(token)
  } catch (e) {
    showFailure(e, invitationRefusalOnOpen)
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
    showFailure(e, invitationRefusalOnAnswer)
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
    showFailure(e, invitationRefusalOnAnswer)
  } finally {
    isProcessing.value = false
  }
}
</script>
