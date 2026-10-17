<template>
  <i18n-t
    v-if="translations.length > 0"
    keypath="publication.show.translations"
    tag="p"
    class="text-sm text-surface-600 dark:text-surface-300"
  >
    <template #languages>
      <span class="inline-flex flex-wrap gap-x-3">
        <router-link
          v-for="translation in translations"
          :key="translation.language"
          :to="{ name: route.name, params: { slug: translation.slug } }"
          :lang="translation.language"
          :hreflang="translation.language"
          class="font-semibold text-primary hover:opacity-75 transition-opacity"
        >
          {{ languageName(translation.language) }}
        </router-link>
      </span>
    </template>
  </i18n-t>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { languageName } from '../../i18n/languageName.js'

// A translation is the same kind of page as this one (an article or a course), so it shares its route.
defineProps({
  translations: { type: Array, default: () => [] }
})

const route = useRoute()
</script>
