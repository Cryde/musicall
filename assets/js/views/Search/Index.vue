<template>
  <div class="flex justify-end">
    <breadcrumb :items="[{'label':  'Rechercher un musicien'}]" />
  </div>

  <div class="flex justify-between mb-10">
    <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
      Rechercher un musicien
    </h1>

    <div class="flex flex-wrap justify-end-safe gap-4">
      <Button label="Poster une annonce" icon="pi pi-megaphone" severity="info" size="small"
              class="whitespace-nowrap"/>
    </div>
  </div>



</template>
<script setup>
import Breadcrumb from "../Global/Breadcrumb.vue";
import Button from "primevue/button";
</script>