<template>
  <PublicationDetailLayout
    v-if="publication"
    :publication="publication"
    :related-publications="relatedPublications"
    :breadcrumb-items="breadCrumbs"
    category-route-name="app_publications_by_category"
    :related-section-title="t('publication.show.related_title')"
    :share-url="shareUrl"
    :share-title="shareTitle"
  />
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import PublicationDetailLayout from '../../components/Publication/PublicationDetailLayout.vue'
import { usePublicationStore } from '../../store/publication/publication.js'

const { t } = useI18n()
const route = useRoute()
const publicationStore = usePublicationStore()
const { publication, relatedPublications } = storeToRefs(publicationStore)

const pageTitle = computed(() =>
  publication.value
    ? t('publication.show.page_title', { title: publication.value.title })
    : t('publication.show.page_title_empty')
)

useTitle(pageTitle)

async function loadData(slug) {
  await publicationStore.loadPublication(slug)
  trackUmamiEvent('publication-view', { publication: slug })
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
  { label: t('publication.common.publications'), to: { name: 'app_publications' } },
  {
    label: publication.value.category.title,
    to: {
      name: 'app_publications_by_category',
      params: { slug: publication.value.category.slug }
    }
  },
  { label: publication.value.title }
])

const shareUrl = computed(() => window.location.href)
const shareTitle = pageTitle
</script>
