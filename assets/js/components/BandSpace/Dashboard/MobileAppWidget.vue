<template>
  <section
    v-if="isOffered"
    class="relative bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 lg:p-6 flex flex-col gap-4"
    :aria-labelledby="titleId"
  >
    <Button
      icon="pi pi-times"
      text
      rounded
      severity="secondary"
      size="small"
      class="!absolute top-3 right-3"
      :aria-label="t('band_space.dashboard.mobile_app.dismiss')"
      @click="isDismissed = true"
    />
    <h2 :id="titleId" class="m-0 pr-8 font-semibold text-base lg:text-lg flex items-center gap-2 text-surface-900 dark:text-surface-0">
      <i class="pi pi-mobile text-xl text-primary" aria-hidden="true" />
      {{ t('band_space.dashboard.mobile_app.title') }}
    </h2>
    <p class="m-0 text-sm leading-relaxed text-surface-600 dark:text-surface-300">
      {{ t('band_space.dashboard.mobile_app.body') }}
    </p>
    <div class="flex items-center justify-between gap-4">
      <MobileAppBadges placement="band-space-dashboard" />
      <MobileAppQrCode class="hidden lg:flex" />
    </div>
  </section>
</template>

<script setup>
import { useLocalStorage } from '@vueuse/core'
import Button from 'primevue/button'
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { offersMobileApp } from '../../../utils/mobileApp.js'
import MobileAppBadges from '../../MobileApp/MobileAppBadges.vue'
import MobileAppQrCode from '../../MobileApp/MobileAppQrCode.vue'

const { t } = useI18n()
const titleId = useId()
const userSecurityStore = useUserSecurityStore()
const isDismissed = useLocalStorage('band-space-mobile-app-dismissed', false)

const isOffered = computed(
  () => !isDismissed.value && offersMobileApp(userSecurityStore.userProfile)
)
</script>
