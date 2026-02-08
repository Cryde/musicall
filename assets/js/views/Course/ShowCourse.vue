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
            <router-link
              v-if="!publication.author.deletion_datetime"
              :to="{ name: 'app_user_public_profile', params: { username: publication.author.username } }"
              class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
            >{{ authorName }}</router-link>
            <span v-else class="font-semibold text-surface-500">{{ authorName }}</span>
            le {{ relativeDate(publication.publication_datetime) }}
          </div>
        </div>
        <ShareButton :url="shareUrl" :title="shareTitle" />
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
            <router-link
              v-if="!publication.author.deletion_datetime"
              :to="{ name: 'app_user_public_profile', params: { username: publication.author.username } }"
              class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
            >{{ authorName }}</router-link>
            <span v-else class="font-semibold text-surface-500">{{ authorName }}</span>
            le {{ relativeDate(publication.publication_datetime) }}
          </div>
        </div>
        <ShareButton :url="shareUrl" :title="shareTitle" />
      </div>

      <figure class="mt-7 w-full">
        <iframe
          class="has-ratio aspect-video w-full border-0"
          :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`"
          allowfullscreen
        />
      </figure>
    </template>

    <CommentThread
      v-if="publication.thread?.id"
      :thread-id="publication.thread.id"
    />
  </template>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import CommentThread from '../../components/Comment/CommentThread.vue'
import ShareButton from '../../components/ShareButton.vue'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { usePublicationStore } from '../../store/publication/publication.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const route = useRoute()
const publicationStore = usePublicationStore()
const { publication } = storeToRefs(publicationStore)

const authorName = computed(() => publication.value ? displayName(publication.value.author) : '')

useTitle(() =>
  publication.value ? `${publication.value.title} - Cours - MusicAll` : 'Cours - MusicAll'
)

onMounted(async () => {
  await publicationStore.loadPublication(route.params.slug)
  trackUmamiEvent('course-view', { course: route.params.slug })
})

const breadCrumbs = computed(() => {
  return [
    { label: 'Cours', to: { name: 'app_course' } },
    {
      label: publication.value.category.title,
      to: { name: 'app_course_by_category', params: { slug: publication.value.category.slug } }
    },
    { label: publication.value.title }
  ]
})

const shareUrl = computed(() => window.location.href)

const shareTitle = computed(() => {
  if (publication.value) {
    return `${publication.value.title} - Cours - MusicAll`
  }
  return 'Cours - MusicAll'
})
</script>
