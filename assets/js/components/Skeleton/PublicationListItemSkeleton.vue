<template>
  <div
    class="h-auto md:h-[160px] p-1 md:p-2 bg-white dark:bg-surface-900 rounded-2xl shadow-sm flex flex-col md:flex-row gap-4"
  >
    <Skeleton class="h-[130px] md:h-full md:w-3/12 rounded-lg" />
    <div class="p-2 md:p-4 flex flex-col gap-4 w-full md:w-9/12">
      <div class="flex flex-col gap-4 flex-1">
        <div class="self-stretch md:h-[51px] flex flex-col gap-2">
          <Skeleton width="70%" height="1.5rem" />
          <Skeleton width="90%" height="1rem" />
        </div>
      </div>
      <div class="flex items-center gap-3">
        <Skeleton width="40%" height="0.875rem" />
        <Skeleton width="4rem" height="1.5rem" borderRadius="1rem" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
</script>
