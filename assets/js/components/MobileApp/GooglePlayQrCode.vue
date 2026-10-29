<template>
  <figure class="m-0 flex flex-col items-center gap-2">
    <img :src="qrCode" :alt="t('shared.mobile_app.qr_alt')" width="112" height="112" class="w-28 h-28 rounded-lg bg-white p-1.5" />
    <figcaption class="text-xs text-center text-surface-600 dark:text-surface-400">{{ t('shared.mobile_app.qr_caption') }}</figcaption>
  </figure>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import qrCode from '../../../image/mobile-app/google-play-qr.svg'

const { t } = useI18n()
</script>
