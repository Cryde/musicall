<template>
  <p
    class="m-0 flex items-start gap-2.5 rounded-xl px-4 py-3 text-sm font-semibold bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
  >
    <i class="pi pi-info-circle mt-0.5" aria-hidden="true" />{{ t('profile.owner.banner') }}
  </p>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** Above the wall on the owner's own profile (#1317): how to edit it. */
const { t } = useI18n()
</script>
