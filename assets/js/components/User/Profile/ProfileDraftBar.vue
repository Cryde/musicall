<template>
  <div
    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl px-4 py-2.5 text-sm bg-amber-50 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200"
  >
    <span class="inline-flex items-center gap-2 font-semibold">
      <i class="pi pi-pencil" aria-hidden="true" />{{ t('profile.teacher_page.draft_notice') }}
    </span>
    <Button
      as="router-link"
      :to="{ name: 'app_user_services' }"
      :label="t('profile.teacher_page.draft_manage')"
      icon="pi pi-briefcase"
      size="small"
      severity="secondary"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/** A draft service's owner, on its page (#1406): only they see it, until they publish it from « Mes services ». */
const { t } = useI18n()
</script>
