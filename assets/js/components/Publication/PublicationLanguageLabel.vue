<template>
  <span v-if="language" :lang="language">{{ languageName(language) }}</span>
  <span v-else class="text-surface-500 dark:text-surface-400">{{ t('publication.common.every_language') }}</span>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { languageName } from '../../i18n/languageName.js'

// Null for a video, which is shown in every language.
defineProps({
  language: { type: String, default: null }
})

const { t } = useI18n()
</script>
