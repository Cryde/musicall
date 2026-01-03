<template>
  <Card class="mb-4">
    <template #content>
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex md:flex-col items-center gap-3 md:w-24 shrink-0">
          <Skeleton shape="circle" size="3rem" />
          <Skeleton width="60px" height="0.875rem" />
        </div>

        <div class="flex-1">
          <Skeleton width="150px" height="0.875rem" class="mb-3" />
          <Skeleton width="100%" height="1rem" class="mb-2" />
          <Skeleton width="100%" height="1rem" class="mb-2" />
          <Skeleton width="80%" height="1rem" class="mb-2" />
          <Skeleton width="60%" height="1rem" />
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
</script>
