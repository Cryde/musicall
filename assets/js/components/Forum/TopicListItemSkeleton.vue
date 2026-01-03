<template>
  <Card class="mb-2">
    <template #content>
      <div class="flex flex-col md:flex-row md:items-center py-2 gap-2 md:gap-4">
        <div class="flex-1">
          <Skeleton width="300px" height="1.25rem" class="mb-2" />
          <Skeleton width="200px" height="0.875rem" />
        </div>
        <div class="shrink-0">
          <Skeleton width="40px" height="1rem" />
        </div>
        <div class="md:w-48 shrink-0">
          <Skeleton width="150px" height="0.875rem" class="mb-1" />
          <Skeleton width="120px" height="0.875rem" />
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
</script>
