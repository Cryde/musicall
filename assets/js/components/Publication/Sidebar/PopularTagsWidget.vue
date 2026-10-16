<template>
  <section class="border border-surface-200 dark:border-surface-700 rounded-lg overflow-hidden bg-surface-0 dark:bg-surface-900">
    <header class="flex items-center gap-2 px-4 py-3 border-b border-surface-200 dark:border-surface-700">
      <i class="pi pi-tags text-primary" />
      <h2 class="text-sm font-semibold uppercase tracking-wide text-surface-700 dark:text-surface-200">
        {{ t('publication.sidebar.popular_tags') }}
      </h2>
    </header>

    <div v-if="isLoading" class="p-4 flex flex-wrap gap-2">
      <Skeleton v-for="i in 6" :key="i" width="4rem" height="1.5rem" />
    </div>

    <div v-else-if="tags.length > 0" class="p-4 flex flex-wrap gap-2">
      <router-link
        v-for="tag in tags"
        :key="tag.slug"
        :to="{ name: 'app_publication_tag', params: { slug: tag.slug } }"
        class="inline-flex transition-opacity hover:opacity-75"
        :aria-label="t('publication.common.tag_link', { tag: tag.label })"
      >
        <Tag :value="`#${tag.label}`" severity="secondary" />
      </router-link>
    </div>

    <p v-else class="px-4 py-4 text-sm text-surface-500 dark:text-surface-400">
      {{ t('publication.sidebar.no_tags') }}
    </p>
  </section>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import tagsApi from '../../../api/tag/tags.js'

const props = defineProps({
  count: { type: Number, default: 8 }
})

const { t } = useI18n()

const tags = ref([])
const isLoading = ref(false)

onMounted(async () => {
  isLoading.value = true
  try {
    tags.value = await tagsApi.getPopularTags({ count: props.count })
  } finally {
    isLoading.value = false
  }
})
</script>
