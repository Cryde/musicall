<template>
  <!-- A globe rather than a word: it reads the same whatever language the visitor is stuck in. -->
  <button
    type="button"
    class="flex items-center justify-center w-10 h-10 rounded-full cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
    :aria-label="t('locale_switcher.label', { language: endonym(activeLocale) })"
    aria-haspopup="menu"
    :aria-expanded="isMenuOpen"
    :aria-controls="menuId"
    @click="menu?.toggle($event)"
  >
    <i class="pi pi-globe text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
  </button>
  <!-- The menu item, not its content, carries the accessible name, so lang and aria-current go on it. -->
  <Menu
    :id="menuId"
    ref="menu"
    :model="items"
    :popup="true"
    :aria-label="t('locale_switcher.menu')"
    :pt="{
      item: ({ context }) => ({
        lang: context.item.locale,
        'aria-current': context.item.locale === activeLocale ? 'true' : undefined
      })
    }"
    @show="isMenuOpen = true"
    @hide="isMenuOpen = false"
  >
    <template #item="{ item, props: itemProps }">
      <a v-bind="itemProps.action" class="flex items-center gap-2">
        <span>{{ item.label }}</span>
        <i v-if="item.locale === activeLocale" class="pi pi-check ml-auto" aria-hidden="true" />
      </a>
    </template>
  </Menu>
</template>

<script setup>
import Menu from 'primevue/menu'
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { LOCALE_OPTIONS, useLocaleChoice } from '../../composables/useLocaleChoice.js'
import { endonym } from '../../i18n/localeChoice.js'

const emit = defineEmits(['chosen'])

const { t } = useI18n()
const { activeLocale, isSwitching, chooseLocale } = useLocaleChoice()

const menu = ref(null)
const menuId = useId()
const isMenuOpen = ref(false)

const items = computed(() =>
  LOCALE_OPTIONS.map(({ locale, label }) => ({
    locale,
    label,
    disabled: isSwitching.value,
    command: async () => {
      await chooseLocale(locale)
      emit('chosen')
    }
  }))
)
</script>
