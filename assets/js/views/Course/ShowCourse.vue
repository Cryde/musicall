<template>
  <PublicationDetailLayout
    v-if="publication"
    :publication="publication"
    :related-publications="relatedPublications"
    :breadcrumb-items="breadCrumbs"
    category-route-name="app_course_by_category"
    :related-section-title="t('publication.course.show.related_title')"
    :share-url="shareUrl"
    :share-title="shareTitle"
    :latest-title="t('publication.course.show.latest_title')"
    latest-icon="pi-graduation-cap"
    :latest-sub-category-type="2"
    :latest-empty-message="t('publication.course.show.latest_empty')"
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
    ? t('publication.course.show.page_title', { title: publication.value.title })
    : t('publication.course.show.page_title_empty')
)

useTitle(pageTitle)

/** @returns {Promise<boolean>} whether it moved the page under the publication's language */
async function loadData(slug) {
  const loadedPath = route.path
  await publicationStore.loadPublication(slug)
  if (moveToContentLanguage(publication.value?.language, loadedPath)) {
    return true
  }
  trackUmamiEvent('course-view', { course: slug })
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
  { label: t('publication.course.list.heading'), to: { name: 'app_course' } },
  {
    label: publication.value.category.title,
    to: { name: 'app_course_by_category', params: { slug: publication.value.category.slug } }
  },
  { label: publication.value.title }
])

const shareUrl = computed(() => window.location.href)
const shareTitle = pageTitle
</script>
