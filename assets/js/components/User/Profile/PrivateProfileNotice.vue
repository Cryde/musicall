<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
    <i class="pi pi-lock text-4xl text-surface-400 mb-4" aria-hidden="true" />
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
      {{ t('profile.public.private_title') }}
    </h2>
    <p class="text-surface-500 dark:text-surface-400 mb-4">
      {{ t('profile.public.private_help') }}
    </p>
    <Button as="router-link" :to="{ name: 'app_home' }" :label="t('profile.public.back_home')" icon="pi pi-home" severity="info" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/** What a visitor gets for a private profile; its owner sees the profile itself (#1317). */
const { t } = useI18n()
</script>
