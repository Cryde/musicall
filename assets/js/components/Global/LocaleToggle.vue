<template>
  <div class="flex items-center gap-3 px-3 py-1.5">
    <i class="pi pi-globe w-5 shrink-0 text-center text-base text-surface-700 dark:text-surface-300" aria-hidden="true" />
    <!-- Drawn like a SelectButton without loading one: every page carries the phone menu. -->
    <div role="group" :aria-label="t('locale_switcher.menu')" class="flex flex-1 gap-1 rounded-md bg-surface-100 dark:bg-surface-950 p-1">
      <button
        v-for="option in LOCALE_OPTIONS"
        :key="option.locale"
        type="button"
        :lang="option.locale"
        :aria-pressed="option.locale === activeLocale"
        :disabled="isSwitching"
        :class="[
          'flex-1 rounded px-3 py-1 text-sm font-medium transition-colors cursor-pointer disabled:cursor-wait',
          option.locale === activeLocale
            ? 'bg-surface-0 dark:bg-surface-800 text-surface-900 dark:text-surface-0 shadow-sm'
            : 'text-surface-600 dark:text-surface-300 hover:text-surface-900 dark:hover:text-surface-0'
        ]"
        @click="option.locale !== activeLocale && chooseLocale(option.locale)"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { LOCALE_OPTIONS, useLocaleChoice } from '../../composables/useLocaleChoice.js'

/** The languages side by side, for the phone menu, where a popup menu would open beneath the drawer. */
const { t } = useI18n()
const { activeLocale, isSwitching, chooseLocale } = useLocaleChoice()
</script>
