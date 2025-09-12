<template>

  <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
    <div class="flex flex-col gap-2">
      <h2 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0"> Dernières publications</h2>
    </div>
  </div>

  <div class="flex flex-row">
    <div class="basis-3/4">

      <div class="flex flex-wrap justify-end-safe gap-4 mb-10">
        <Button label="Poster une découverte" icon="pi pi-plus" severity="info" size="small"
                class="whitespace-nowrap"/>
        <Button label="Poster une publication" icon="pi pi-plus" severity="info" size="small"
                class="whitespace-nowrap"/>
      </div>

      <div class="self-stretch flex flex-col gap-8">
        <div class="grid grid-cols-1 xl:grid-cols-1 gap-3">
          <PublicationListItem
              v-for="publication in publicationsStore.publications"
              :key="publication.id"
              :cover="publication.cover"
              :title="publication.title"
              :description="publication.description"
              :category="publication.sub_category"
              :author="publication.author"
              :date="publication.publication_datetime"/>
        </div>
      </div>
    </div>
    <div class="basis-1/4"></div>
  </div>
</template>
<script setup>
import {onUnmounted} from "vue";
import Button from 'primevue/button';
import {usePublicationsStore} from "../../store/publication/publications.js";
import PublicationListItem from "../Publication/PublicationListItem.vue";

const publicationsStore = usePublicationsStore();
publicationsStore.loadPublications({page: 1})
publicationsStore.loadCategories()

onUnmounted(() => {
  publicationsStore.clear();
})
</script>