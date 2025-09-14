<template>

  <div class="flex justify-end" v-if="publicationStore.publication">
    <Breadcrumb
        :items="[{'label':  'Publications'}, {'label':  publicationStore.publication.category.title}, {'label':  publicationStore.publication.title}]" />
  </div>

  <div v-if="publicationStore.publication">

    <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
          {{ publicationStore.publication.title }}</h1>
        <div class="text-sm leading-tight text-surface-500 dark:text-surface-300 mt-5">
          Publié par
          <strong>{{ publicationStore.publication.author.username }}</strong>
          le {{ relativeDate(publicationStore.publication.publication_datetime) }}
        </div>
      </div>
    </div>

    <figure class="mt-7 w-full">
      <iframe class="has-ratio aspect-video w-full"
              :src="`https://www.youtube.com/embed/${publicationStore.publication.content}?showinfo=0`" frameborder="0"
              allowfullscreen></iframe>
    </figure>
  </div>
</template>

<script setup>
import {usePublicationStore} from "../../store/publication/publication.js";
import {useRoute} from 'vue-router'
import relativeDate from "../../helper/date/relative-date.js";
import Breadcrumb from "../Global/Breadcrumb.vue";

const route = useRoute()
const publicationStore = usePublicationStore();
publicationStore.loadPublication(route.params.slug);
</script>
