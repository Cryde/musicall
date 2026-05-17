<template>
  <section class="border border-surface-200 dark:border-surface-700 rounded-lg overflow-hidden bg-surface-0 dark:bg-surface-900">
    <header class="flex items-center gap-2 px-4 py-3 border-b border-surface-200 dark:border-surface-700">
      <i :class="['pi', icon, 'text-primary']" />
      <h2 class="text-sm font-semibold uppercase tracking-wide text-surface-700 dark:text-surface-200">
        {{ title }}
      </h2>
    </header>

    <div v-if="isLoading" class="p-4 space-y-3">
      <Skeleton v-for="i in 3" :key="i" height="3rem" />
    </div>

    <ul v-else-if="items.length > 0" class="divide-y divide-surface-200 dark:divide-surface-700">
      <li
        v-for="item in items"
        :key="item.slug"
        class="px-4 py-3"
      >
        <router-link
          :to="{ name: routeNameFor(item), params: { slug: item.slug } }"
          class="flex items-start gap-3 group"
        >
          <img
            v-if="item.cover"
            :src="item.cover"
            :alt="item.title"
            class="w-12 h-12 rounded object-cover flex-shrink-0"
            loading="lazy"
          />
          <div v-else class="w-12 h-12 rounded bg-surface-100 dark:bg-surface-800 flex-shrink-0 flex items-center justify-center">
            <i class="pi pi-image text-surface-400" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-surface-900 dark:text-surface-0 line-clamp-2 group-hover:text-primary transition-colors">
              {{ item.title }}
            </p>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
              {{ relativeDate(item.publication_datetime) }}
            </p>
          </div>
        </router-link>
      </li>
    </ul>

    <p v-else class="px-4 py-4 text-sm text-surface-500 dark:text-surface-400">
      {{ emptyMessage }}
    </p>
  </section>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import { onMounted, ref, watch } from 'vue'
import publicationsApi from '../../../api/publication/publications.js'
import relativeDate from '../../../helper/date/relative-date.js'

const props = defineProps({
  excludeId: { type: Number, default: null },
  count: { type: Number, default: 3 },
  subCategoryType: { type: Number, default: null },
  title: { type: String, default: 'Dernières publications' },
  icon: { type: String, default: 'pi-clock' },
  emptyMessage: { type: String, default: 'Aucune publication récente.' }
})

const items = ref([])
const isLoading = ref(false)

function routeNameFor(item) {
  return item.sub_category?.is_course ? 'app_course_show' : 'app_publication_show'
}

async function load() {
  isLoading.value = true
  try {
    items.value = await publicationsApi.getLatestPublications({
      excludeId: props.excludeId,
      count: props.count,
      subCategoryType: props.subCategoryType
    })
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
watch(() => [props.excludeId, props.count, props.subCategoryType], load)
</script>
