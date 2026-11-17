<template>
  <button
    type="button"
    class="group block text-left rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 cursor-pointer"
    :aria-label="media.title ? t('profile.media.play', { title: media.title }) : t('profile.media.play_untitled')"
    @click="emit('play', media)"
  >
    <span class="relative block aspect-video bg-surface-200 dark:bg-surface-700">
      <img
        v-if="media.thumbnail_url"
        :src="media.thumbnail_url"
        alt=""
        class="w-full h-full object-cover"
      />
      <span class="absolute inset-0 m-auto w-14 h-14 rounded-full bg-black/60 text-white flex items-center justify-center group-hover:bg-black/75">
        <i class="pi pi-play text-xl" aria-hidden="true" />
      </span>
    </span>
    <span class="block px-3 py-2.5">
      <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">
        {{ label }}
      </span>
      <span v-if="media.title" class="block text-xs text-surface-500 dark:text-surface-400 truncate">{{ media.title }}</span>
    </span>
  </button>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** The first media of a service page, in its header: a click plays it in place. */
defineProps({
  media: { type: Object, required: true },
  label: { type: String, required: true }
})

const emit = defineEmits(['play'])
const { t } = useI18n()
</script>
