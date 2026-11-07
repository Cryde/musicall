<template>
  <a
    :href="badge.url"
    target="_blank"
    rel="noopener noreferrer"
    class="inline-block shrink-0"
    @click="trackUmamiEvent('mobile-app-click', { placement, store })"
  >
    <img :src="badge.src" :alt="t(badge.altKey)" :width="badge.width" :height="badge.height" class="h-full w-auto" />
  </a>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import appStoreEn from '../../../image/mobile-app/app-store-badge-en.svg'
import appStoreFr from '../../../image/mobile-app/app-store-badge-fr.svg'
import googlePlayEn from '../../../image/mobile-app/google-play-badge-en.webp'
import googlePlayFr from '../../../image/mobile-app/google-play-badge-fr.webp'
import { APP_STORE_URL, PLAY_STORE_URL } from '../../utils/mobileApp.js'

const props = defineProps({
  /** `android` or `ios`. */
  store: { type: String, required: true },
  // Where the badge sits, so the analytics tell which spot brings the downloads.
  placement: { type: String, required: true }
})

const { t, locale } = useI18n()

// Each store's own artwork, unaltered as its badge guidelines require, with its intrinsic size per language.
const BADGES = {
  android: {
    url: PLAY_STORE_URL,
    altKey: 'shared.mobile_app.badge_alt.android',
    images: {
      fr: { src: googlePlayFr, width: 646, height: 192 },
      en: { src: googlePlayEn, width: 564, height: 168 }
    }
  },
  ios: {
    url: APP_STORE_URL,
    altKey: 'shared.mobile_app.badge_alt.ios',
    images: {
      fr: { src: appStoreFr, width: 127, height: 40 },
      en: { src: appStoreEn, width: 120, height: 40 }
    }
  }
}

const badge = computed(() => {
  const { images, ...rest } = BADGES[props.store]
  return { ...rest, ...(locale.value === 'en' ? images.en : images.fr) }
})
</script>
