<template>
  <div class="mb-8">
    <Skeleton width="200px" height="1.5rem" class="mb-2" />
    <Skeleton width="300px" height="1rem" class="mb-4" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="i in 3" :key="i" class="h-full">
        <template #title>
          <Skeleton width="150px" height="1.25rem" />
        </template>
        <template #content>
          <Skeleton width="100%" height="0.875rem" class="mb-2" />
          <Skeleton width="80%" height="0.875rem" />
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
</script>
