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
import { useCommentDraftResumer } from '../../composables/useCommentDraft.js'
import { useContentLanguage } from '../../composables/useContentLanguage.js'
import { useContentPageLoad } from '../../composables/useContentPageLoad.js'
import { usePublicationStore } from '../../store/publication/publication.js'

const { t } = useI18n()
const route = useRoute()
const publicationStore = usePublicationStore()
const { publication, relatedPublications } = storeToRefs(publicationStore)
const moveToContentLanguage = useContentLanguage()
const pageLoad = useContentPageLoad()

const pageTitle = computed(() =>
  publication.value
    ? t('publication.show.page_title', { title: publication.value.title })
    : t('publication.show.page_title_empty')
)

useTitle(pageTitle)

/** @returns {Promise<boolean>} whether it moved the page under the publication's language */
async function loadData(slug) {
  const loadedPath = route.path
  await publicationStore.loadPublication(slug)
  if (moveToContentLanguage(publication.value?.language, loadedPath)) {
    return true
  }
  trackUmamiEvent('publication-view', { publication: slug })
  publicationStore.loadRelatedPublications(slug)
  return false
}

onMounted(() => {
  pageLoad.track(loadData(route.params.slug))
})

watch(
  () => route.params.slug,
  (newSlug) => {
    if (newSlug) {
      pageLoad.track(loadData(newSlug))
    }
  }
)

// A comment written before signing in comes back in the form (#1305).
useCommentDraftResumer({ publication, whenLoaded: pageLoad.whenLoaded })

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
