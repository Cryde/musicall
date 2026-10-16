<template>
  <section
    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-gradient-to-r from-primary-50 to-purple-50 dark:from-surface-900 dark:to-surface-900 p-5"
    aria-labelledby="home-member-band-strip-title"
  >
    <div class="flex items-center gap-3.5">
      <span class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-primary-400 to-fuchsia-400" aria-hidden="true" />
      <div class="flex flex-col gap-0.5">
        <h2 id="home-member-band-strip-title" class="m-0 text-base font-bold text-surface-900 dark:text-surface-0">
          {{ t('home.member.band_space_strip.title') }}
        </h2>
        <p class="m-0 text-sm text-surface-700 dark:text-surface-300">
          {{ t('home.member.band_space_strip.intro') }}
        </p>
      </div>
    </div>
    <div class="flex flex-wrap gap-2 shrink-0">
      <Button as="router-link" :to="{ name: 'app_band_index' }" :label="t('home.member.band_space_strip.create')" icon="pi pi-plus" size="small" />
      <Button
        as="router-link"
        :to="{ name: 'app_band_space_presentation' }"
        :label="t('home.member.band_space_strip.discover')"
        severity="secondary"
        outlined
        size="small"
      />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/** The Band Space for a member who has none yet (#1078): one line, rather than a big empty card. */
const { t } = useI18n()
</script>
