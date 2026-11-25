<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
      <i class="pi pi-video text-surface-400" aria-hidden="true" />{{ t('profile.service_page.media') }}
    </h2>
    <div class="grid gap-4 sm:grid-cols-2">
      <ServiceHeroMedia v-for="item in media" :key="item.id" :media="item" :label="item.title || t('profile.media.play_untitled')" @play="emit('play', $event)" />
    </div>
  </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import ServiceHeroMedia from './ServiceHeroMedia.vue'

/** A service's media past the first, which its header plays (#1410). */
defineProps({
  media: { type: Array, required: true }
})
/** `play` with the media, which the page plays in its own player. */
const emit = defineEmits(['play'])

const { t } = useI18n()
</script>
