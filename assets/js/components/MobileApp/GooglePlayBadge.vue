<template>
  <a
    :href="PLAY_STORE_URL"
    target="_blank"
    rel="noopener noreferrer"
    class="inline-block shrink-0"
    @click="trackUmamiEvent('mobile-app-click', { placement })"
  >
    <img :src="badge" :alt="t('shared.mobile_app.badge_alt')" width="646" height="192" class="h-full w-auto" />
  </a>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import badgeEn from '../../../image/mobile-app/google-play-badge-en.webp'
import badgeFr from '../../../image/mobile-app/google-play-badge-fr.webp'
import { PLAY_STORE_URL } from '../../utils/mobileApp.js'

// Where the badge sits, so the analytics tell which spot brings the downloads.
defineProps({
  placement: { type: String, required: true }
})

const { t, locale } = useI18n()

// Google's own artwork, unaltered as its badge guidelines require.
const badge = computed(() => (locale.value === 'en' ? badgeEn : badgeFr))
</script>
