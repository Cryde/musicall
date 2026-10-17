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
          :to="translationLocation(translation)"
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
import { useRoute, useRouter } from 'vue-router'
import { languageName } from '../../i18n/languageName.js'
import { contentLanguageLocation } from '../../i18n/localizedLocation.js'

// A translation is the same kind of page as this one (an article or a course), so it shares its route.
defineProps({
  translations: { type: Array, default: () => [] }
})

const route = useRoute()
const router = useRouter()

// Under the prefix of the translation's language, where its page would redirect once loaded: a `{ name }`
// link inherits this page's locale, so it would point an English reader's French link at /en/.
function translationLocation(translation) {
  const link = router.resolve({ name: route.name, params: { slug: translation.slug } })

  return contentLanguageLocation(link, translation.language, link.path) ?? link
}
</script>
