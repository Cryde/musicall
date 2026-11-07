<template>
  <section
    v-if="styles.length"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-styles-title"
  >
    <h2 id="profile-styles-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.musician_page.styles') }}
    </h2>
    <ul class="flex flex-wrap gap-1.5 list-none m-0 p-0">
      <li
        v-for="style in styles"
        :key="style.id"
        class="inline-flex items-center h-7 px-2.5 rounded-lg text-[13px] font-semibold bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
      >
        {{ style.name }}
      </li>
    </ul>
  </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** The styles a musician plays (#1317). */
defineProps({
  styles: { type: Array, default: () => [] }
})

const { t } = useI18n()
</script>
