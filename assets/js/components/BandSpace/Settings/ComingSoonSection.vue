<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8">
    <div class="flex flex-col items-center justify-center py-12 text-center">
      <i class="pi pi-wrench text-4xl text-surface-500 dark:text-surface-400 mb-4"></i>
      <h3 class="text-lg font-semibold text-surface-700 dark:text-surface-200 mb-2">{{ title }}</h3>
      <p class="text-surface-500 dark:text-surface-400">
        {{ t('band_space.settings.coming_soon') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  title: { type: String, required: true }
})

const { t } = useI18n()
</script>
