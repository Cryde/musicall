<template>
  <!-- The languages as plain buttons, for a drawer: a popup menu would open beneath it. -->
  <ul :aria-label="t('locale_switcher.menu')" class="list-none p-0 m-0 flex flex-col gap-1">
    <li v-for="option in LOCALE_OPTIONS" :key="option.locale">
      <button
        type="button"
        :lang="option.locale"
        :aria-current="option.locale === activeLocale ? 'true' : undefined"
        :disabled="isSwitching"
        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer disabled:cursor-wait"
        @click="choose(option.locale)"
      >
        <i class="pi pi-globe text-base shrink-0" aria-hidden="true" />
        <span class="font-medium truncate">{{ option.label }}</span>
        <i v-if="option.locale === activeLocale" class="pi pi-check text-sm ml-auto" aria-hidden="true" />
      </button>
    </li>
  </ul>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { LOCALE_OPTIONS, useLocaleChoice } from '../../composables/useLocaleChoice.js'

const emit = defineEmits(['chosen'])

const { t } = useI18n()
const { activeLocale, isSwitching, chooseLocale } = useLocaleChoice()

async function choose(locale) {
  await chooseLocale(locale)
  emit('chosen')
}
</script>
