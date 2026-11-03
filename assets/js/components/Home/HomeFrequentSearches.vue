<template>
  <nav v-if="links.length > 0" class="flex flex-wrap items-center gap-2" :aria-label="t('home.frequent_searches.aria_label')">
    <span class="text-sm text-surface-700 dark:text-surface-300">{{ t('home.frequent_searches.label') }}</span>
    <router-link
      v-for="link in links"
      :key="link.label"
      :to="link.route"
      class="rounded-full border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 px-3 py-1 text-sm text-surface-800 dark:text-surface-100 transition-colors duration-150 hover:bg-surface-100 dark:hover:bg-surface-700"
    >
      {{ link.label }}
    </router-link>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { frequentSearchLink } from '../../utils/homeSearch.js'

/** What enough different people searched for lately (#1075); nothing at all when there is none. */
const props = defineProps({
  searches: { type: Array, default: () => [] }
})

const { t } = useI18n()

const links = computed(() => props.searches.map(frequentSearchLink))
</script>
