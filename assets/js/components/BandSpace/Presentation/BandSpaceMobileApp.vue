<template>
  <section
    v-if="isOffered"
    class="flex flex-col-reverse md:flex-row md:items-center gap-10 lg:gap-16 p-[18px] md:p-8 lg:p-14 rounded-[20px] md:rounded-3xl max-md:border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
    :aria-labelledby="titleId"
  >
    <!-- A phone drawn inside the phone is 500px of picture; the text says the same. -->
    <PushNotificationsMockup class="self-center max-md:hidden" />
    <div class="flex flex-col gap-3 md:gap-6">
      <div class="flex flex-col gap-3">
        <span class="text-[13px] md:text-sm font-bold tracking-wider uppercase text-primary">{{ t('home.band_space_presentation.mobile_app.eyebrow') }}</span>
        <h2 :id="titleId" class="m-0 text-[22px] md:text-3xl font-extrabold text-surface-900 dark:text-surface-0">
          {{ t('home.band_space_presentation.mobile_app.title') }}
        </h2>
        <p class="m-0 text-[15px] leading-[1.55] md:text-lg md:leading-relaxed text-surface-600 dark:text-surface-300">
          {{ t('home.band_space_presentation.mobile_app.body') }}
        </p>
      </div>
      <ul class="m-0 p-0 list-none grid sm:grid-cols-2 gap-2 md:gap-4">
        <li v-for="feature in features" :key="feature.key">
          <FeatureItem :icon="feature.icon">{{ feature.label }}</FeatureItem>
        </li>
      </ul>
      <div class="flex items-center gap-6">
        <MobileAppBadges placement="band-space-presentation" size="lg" />
        <MobileAppQrCode class="hidden lg:flex" />
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { offersMobileApp } from '../../../utils/mobileApp.js'
import FeatureItem from '../../Discover/FeatureItem.vue'
import MobileAppBadges from '../../MobileApp/MobileAppBadges.vue'
import MobileAppQrCode from '../../MobileApp/MobileAppQrCode.vue'
import PushNotificationsMockup from '../../MobileApp/PushNotificationsMockup.vue'

const { t } = useI18n()
const titleId = useId()
const userSecurityStore = useUserSecurityStore()

const isOffered = computed(() => offersMobileApp(userSecurityStore.userProfile))

const features = computed(() => [
  {
    key: 'chat',
    icon: 'pi pi-comments',
    label: t('home.band_space_presentation.mobile_app.features.chat')
  },
  {
    key: 'setlist',
    icon: 'pi pi-play-circle',
    label: t('home.band_space_presentation.mobile_app.features.setlist')
  },
  {
    key: 'files',
    icon: 'pi pi-cloud-download',
    label: t('home.band_space_presentation.mobile_app.features.files')
  },
  {
    key: 'notes',
    icon: 'pi pi-microphone',
    label: t('home.band_space_presentation.mobile_app.features.notes')
  }
])
</script>
