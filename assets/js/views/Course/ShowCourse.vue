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
  </template>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import relativeDate from '../../helper/date/relative-date.js'
import { usePublicationStore } from '../../store/publication/publication.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const route = useRoute()
const publicationStore = usePublicationStore()
const { publication } = storeToRefs(publicationStore)

onMounted(async () => {
  await publicationStore.loadPublication(route.params.slug)
})

const breadCrumbs = computed(() => {
  return [
    { label: 'Cours' },
    { label: publication.value.category.title },
    { label: publication.value.title }
  ]
})
</script>
