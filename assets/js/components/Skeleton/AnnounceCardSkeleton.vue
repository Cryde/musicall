<template>
  <Card>
    <template #content>
      <div class="flex gap-3">
        <Skeleton shape="circle" size="2.5rem" class="shrink-0" />
        <div class="flex-1 flex flex-col gap-2">
          <Skeleton width="100%" height="1rem" />
          <Skeleton width="80%" height="1rem" />
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
