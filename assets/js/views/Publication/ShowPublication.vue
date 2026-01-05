<template>
  <div
    v-if="publication"
    class="flex justify-end"
  >
    <Breadcrumb :items="breadCrumbs"/>
  </div>

  <template v-if="publication">
    <template v-if="publication.type.label === 'text'">
      <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ publication.title }}</h1>
          <div class="text-sm leading-tight text-surface-500 dark:text-surface-300 mt-5">
            Publié par
            <strong>{{ publication.author.username }}</strong>
            le {{ relativeDate(publication.publication_datetime) }}
          </div>
        </div>
      </div>

      <div
        class="box content is-shadowless publication-container p-3 bg-surface-0 dark:bg-surface-800 rounded-md"
        v-html="publication.content"
      />
    </template>

    <template v-if="publication.type.label === 'video'">
      <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ publication.title }}</h1>
          <div class="text-sm leading-tight text-surface-500 dark:text-surface-300 mt-5">
            Publié par
            <strong>{{ publication.author.username }}</strong>
            le {{ relativeDate(publication.publication_datetime) }}
          </div>
        </div>
      </div>

      <figure class="mt-7 w-full">
        <iframe
          class="has-ratio aspect-video w-full border-0"
          :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`"
          allowfullscreen
        />
      </figure>
    </template>

    <div
      v-if="relatedPublications.length > 0"
      class="mt-10"
    >
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
        Publications similaires
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <PublicationListItem
          v-for="related in relatedPublications"
          :key="related.slug"
          :to-route="{ name: related.sub_category.is_course ? 'app_course_show' : 'app_publication_show', params: { slug: related.slug } }"
          :cover="related.cover"
          :title="related.title"
          :description="related.description"
          :category="related.sub_category"
          :author="related.author"
          :date="related.publication_datetime"
        />
      </div>
    </div>

    <CommentThread
      v-if="publication.thread?.id"
      :thread-id="publication.thread.id"
    />
  </template>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import CommentThread from '../../components/Comment/CommentThread.vue'
import relativeDate from '../../helper/date/relative-date.js'
import { usePublicationStore } from '../../store/publication/publication.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import PublicationListItem from './PublicationListItem.vue'

const route = useRoute()
const publicationStore = usePublicationStore()
const { publication, relatedPublications } = storeToRefs(publicationStore)

async function loadData(slug) {
  await publicationStore.loadPublication(slug)
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

const breadCrumbs = computed(() => {
  return [
    { label: 'Publications', to: { name: 'app_publications' } },
    {
      label: publication.value.category.title,
      to: {
        name: 'app_publications_by_category',
        params: { slug: publication.value.category.slug }
      }
    },
    { label: publication.value.title }
  ]
})
</script>
