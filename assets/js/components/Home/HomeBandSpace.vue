<template>
  <section
    class="grid lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-10 lg:gap-16 items-center rounded-3xl border border-surface-200 dark:border-surface-700 bg-gradient-to-br from-primary-50 to-purple-50 dark:from-surface-900 dark:to-surface-900 p-6 sm:p-10 lg:p-16"
    aria-labelledby="home-band-space-title"
  >
    <div class="flex flex-col gap-6">
      <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm font-bold uppercase tracking-widest text-primary">{{ t('home.band_space.eyebrow') }}</span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-0 dark:bg-surface-800 px-2.5 py-1 text-xs text-surface-700 dark:text-surface-200">
          <i class="pi pi-lock text-[0.7rem]" aria-hidden="true" />
          {{ t('home.band_space.private') }}
        </span>
      </div>
      <h2 id="home-band-space-title" class="m-0 text-3xl lg:text-4xl font-extrabold leading-tight">
        <i18n-t keypath="home.band_space.title" tag="span" class="text-surface-900 dark:text-white">
          <template #highlight>
            <br />
            <span class="text-brand-gradient">{{ t('home.band_space.title_highlight') }}</span>
          </template>
        </i18n-t>
      </h2>
      <p class="m-0 text-lg leading-relaxed text-surface-700 dark:text-surface-300">
        {{ t('home.band_space.intro') }}
      </p>
      <ul class="m-0 p-0 list-none grid sm:grid-cols-2 gap-x-6 gap-y-3">
        <li v-for="module in BAND_SPACE_MODULES" :key="module.key" class="flex items-center gap-3 text-surface-800 dark:text-surface-100">
          <i :class="[module.icon, 'text-primary']" aria-hidden="true" />
          {{ module.label }}
        </li>
      </ul>
      <div class="flex flex-wrap gap-3 pt-1">
        <Button
          as="router-link"
          :to="{ name: 'app_band_space_presentation' }"
          :label="t('home.band_space.discover')"
          icon="pi pi-arrow-right"
          icon-pos="right"
        />
        <Button
          as="router-link"
          :to="{ name: userSecurityStore.isAuthenticated ? 'app_band_index' : 'app_register' }"
          :label="userSecurityStore.isAuthenticated ? t('home.band_space.open_mine') : t('home.band_space.create')"
          severity="secondary"
          outlined
        />
      </div>
    </div>

    <HomeBandSpaceDemo />
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { BAND_SPACE_MODULES } from '../../constants/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import HomeBandSpaceDemo from './HomeBandSpaceDemo.vue'

/** The Band Space pitch on the homepage (#1074); the full tour is its presentation page. */
const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
</script>
