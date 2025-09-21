<template>

  <div class="flex justify-end" v-if="publication">
    <Breadcrumb
        :items="[{'label':  'Publications'}, {'label':  publication.category.title}, {'label':  publication.title}]"/>
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

      <div class="box content is-shadowless publication-container p-3 bg-surface-0 dark:bg-surface-800 rounded-md"
           v-html="publication.content"></div>
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
        <iframe class="has-ratio aspect-video w-full"
                :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`" frameborder="0"
                allowfullscreen></iframe>
      </figure>
    </template>
  </template>
</template>

<script setup>
import {usePublicationStore} from "../../store/publication/publication.js";
import {useRoute} from 'vue-router'
import relativeDate from "../../helper/date/relative-date.js";
import Breadcrumb from "../Global/Breadcrumb.vue";
import {storeToRefs} from "pinia";

const route = useRoute()
const publicationStore = usePublicationStore();
const {publication} = storeToRefs(publicationStore);
publicationStore.loadPublication(route.params.slug);
</script>
