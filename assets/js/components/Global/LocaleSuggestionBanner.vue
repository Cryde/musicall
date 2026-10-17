<template>
  <!-- Written in the language it offers, not the page's, hence its own lang. Fixed rather than in the
       flow: it appears once the auth check answers, and pushing the page down then would shift it. -->
  <div
    v-if="suggestion"
    role="region"
    :aria-label="suggestion.label"
    :lang="suggestion.locale"
    class="fixed bottom-4 inset-x-4 md:inset-x-auto md:right-4 md:max-w-md z-50 flex flex-wrap items-center gap-x-4 gap-y-2 p-4 rounded-lg shadow-lg bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-sm text-surface-700 dark:text-surface-200"
  >
    <span>{{ suggestion.message }}</span>
    <div class="flex items-center gap-1">
      <Button :label="suggestion.accept" size="small" @click="accept" />
      <Button
        icon="pi pi-times"
        :aria-label="suggestion.dismiss"
        severity="secondary"
        text
        rounded
        size="small"
        @click="dismiss"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useLocaleSuggestion } from '../../composables/useLocaleChoice.js'

const { suggestion, accept, dismiss } = useLocaleSuggestion()
</script>
