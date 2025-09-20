<template>
  <div class="relative min-h-[50rem] bg-surface-50 dark:bg-surface-950">
    <Menu />
    <div class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
      <div class="flex flex-col gap-8">
        <router-view/>
      </div>
    </div>

  </div>
</template>

<script setup>
import Menu from './views/Global/Menu.vue';
</script>