<template>
  <div class="flex justify-end">
    <breadcrumb :items="[{'label':  'Rechercher un musicien'}]"/>
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


  <div class="flex flex-row gap-8">
    <div class="basis-7/12">
      <div class="flex flex-col gap-4">
      <Textarea v-model="value3" fluid maxlength="200" rows="5" size="large"
                placeholder="Taper votre recherche ici, exemple: Je recherche un guitariste qui joue du rock"/>

        <div class="flex justify-end">
          <Button label="Rechercher" icon="pi pi-search" severity="info" />
        </div>
      </div>
    </div>
    <div class="basis-5/12">
      <div class="flex flex-col gap-4">
        <Card class="cursor-pointer">
          <template #content>Je cherche un groupe de pop et rock qui a besoin d'un batteur</template>
        </Card>
        <Card class="cursor-pointer">
          <template #content>Je recherche un guitariste pour mon groupe de funk</template>
        </Card>
        <Card class="cursor-pointer">
          <template #content>Je recherche un chanteur pour mon groupe de stoner et métal</template>
        </Card>
      </div>

    </div>
  </div>
</template>
<script setup>
import Breadcrumb from "../Global/Breadcrumb.vue";
import Button from "primevue/button";
import Textarea from "primevue/textarea";
import {ref} from "vue";
import Card from 'primevue/card';

const value3 = ref('');
</script>