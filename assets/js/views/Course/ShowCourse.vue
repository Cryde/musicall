<template>
  <PublicationDetailLayout
    v-if="publication"
    :publication="publication"
    :related-publications="relatedPublications"
    :breadcrumb-items="breadCrumbs"
    category-route-name="app_course_by_category"
    related-section-title="Cours similaires"
    :share-url="shareUrl"
    :share-title="shareTitle"
    latest-title="Derniers cours"
    latest-icon="pi-graduation-cap"
    :latest-sub-category-type="2"
    latest-empty-message="Aucun cours récent."
  />
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import PublicationDetailLayout from '../../components/Publication/PublicationDetailLayout.vue'
import { usePublicationStore } from '../../store/publication/publication.js'

const route = useRoute()
const publicationStore = usePublicationStore()
const { publication, relatedPublications } = storeToRefs(publicationStore)

useTitle(() =>
  publication.value ? `${publication.value.title} - Cours - MusicAll` : 'Cours - MusicAll'
)

async function loadData(slug) {
  await publicationStore.loadPublication(slug)
  trackUmamiEvent('course-view', { course: slug })
  publicationStore.loadRelatedPublications(slug)
}

onMounted(() => {
  loadData(route.params.slug)
})

watch(
  () => route.params.slug,
  (newSlug) => {
    if (newSlug) {
      loadData(newSlug)
    }
  }
)

const breadCrumbs = computed(() => [
  { label: 'Cours', to: { name: 'app_course' } },
  {
    label: publication.value.category.title,
    to: { name: 'app_course_by_category', params: { slug: publication.value.category.slug } }
  },
  { label: publication.value.title }
])

const shareUrl = computed(() => window.location.href)
const shareTitle = computed(() =>
  publication.value ? `${publication.value.title} - Cours - MusicAll` : 'Cours - MusicAll'
)
</script>
