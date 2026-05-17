<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex items-center gap-2 mb-6">
      <i class="pi pi-hashtag text-2xl text-surface-500" />
      <h1 class="text-2xl font-semibold">{{ slug }}</h1>
    </div>

    <div v-if="isLoading && publications.length === 0" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-32 bg-surface-100 dark:bg-surface-800 animate-pulse rounded" />
    </div>

    <div v-else-if="publications.length === 0" class="text-surface-500 dark:text-surface-400 text-center py-12">
      Aucune publication ne porte ce tag pour le moment.
    </div>

    <div v-else class="flex flex-col gap-4">
      <PublicationListItem
        v-for="publication in publications"
        :key="publication.id"
        :to-route="{ name: 'app_publication_show', params: { slug: publication.slug } }"
        :cover="publication.cover"
        :title="publication.title"
        :description="publication.description"
        :category="publication.sub_category"
        :author="publication.author"
        :date="publication.publication_datetime"
        :slug="publication.slug"
        :upvotes="publication.upvotes ?? 0"
        :downvotes="publication.downvotes ?? 0"
        :user-vote="publication.user_vote ?? null"
      />

      <div v-if="hasMore" ref="loadMoreSentinel" class="h-1" />
    </div>
  </div>
</template>

<script setup>
import { useIntersectionObserver, useTitle } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import publicationsApi from '../../api/publication/publications.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import PublicationListItem from './PublicationListItem.vue'

const route = useRoute()

const slug = computed(() => route.params.slug)
const publications = ref([])
const totalItems = ref(0)
const currentPage = ref(1)
const isLoading = ref(false)
const loadMoreSentinel = ref(null)

const hasMore = computed(() => publications.value.length < totalItems.value)

useIntersectionObserver(loadMoreSentinel, ([entry]) => {
  if (entry.isIntersecting && hasMore.value && !isLoading.value) {
    loadPage(currentPage.value + 1)
  }
})

const breadcrumbItems = computed(() => [
  { label: 'Publications', to: { name: 'app_publications' } },
  { label: `#${slug.value}` }
])

useTitle(computed(() => `#${slug.value} - MusicAll`))

async function loadPage(page) {
  isLoading.value = true
  try {
    const data = await publicationsApi.getPublicationsByTag({ slug: slug.value, page })
    if (page === 1) {
      publications.value = data.member
    } else {
      publications.value = [...publications.value, ...data.member]
    }
    totalItems.value = data.totalItems ?? data.member.length
    currentPage.value = page
  } finally {
    isLoading.value = false
  }
}

watch(slug, async () => {
  publications.value = []
  totalItems.value = 0
  currentPage.value = 1
  await loadPage(1)
})

onMounted(() => loadPage(1))
</script>
