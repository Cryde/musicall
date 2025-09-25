<template>
  <div class="flex flex-col dark:border-t dark:border-surface-700 pt-10">
    <div class="flex flex-col lg:flex-row justify-between px-6 md:px-12 lg:px-20 py-16 gap-16 w-full overflow-hidden -mt-3 z-10 relative">
      <div class="flex items-center gap-4">
        <RouterLink
          :to="{name: 'app_home'}"
          class="text-lg text-surface-900 dark:text-surface-0 font-semibold leading-normal"
        >
          MusicAll
        </RouterLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6 w-full max-w-5xl">
        <div class="flex flex-col gap-4">
          <RouterLink
            :to="{name: 'app_publications'}"
            class="text-base font-medium text-surface-900 dark:text-surface-0 leading-normal"
          >
            Publications
          </RouterLink>
        </div>
        <div class="flex flex-col gap-4">
          <RouterLink
            :to="{name: 'app_course'}"
            class="text-base font-medium text-surface-900 dark:text-surface-0 leading-normal"
          >
            Cours
          </RouterLink>
        </div>
        <div class="flex flex-col gap-4">
          <RouterLink
            :to="{name: 'app_search_musician'}"
            class="text-base font-medium text-surface-900 dark:text-surface-0 leading-normal"
          >
            Recherche
          </RouterLink>
        </div>
        <div class="flex flex-col gap-4">
          <RouterLink
              :to="{name: 'app_forum_index'}"
              class="text-base font-medium text-surface-900 dark:text-surface-0 leading-normal">
            Forum
          </RouterLink>
        </div>
      </div>
    </div>
    <div class="py-6 px-6 md:px-12 lg:px-20 flex flex-wrap items-center justify-between w-full gap-4">
      <span class="text-base text-surface-900 dark:text-surface-0 font-normal leading-normal">© 2008-{{ (new Date()).getFullYear() }} MusicAll</span>
      <div class="flex items-center gap-8">
        <a class="cursor-pointer text-surface-900 dark:text-surface-0 hover:opacity-75" href="https://www.instagram.com/music.all.official">
          <i class="pi pi-instagram text-base! leading-normal!" />
        </a>
        <a class="cursor-pointer text-surface-900 dark:text-surface-0 hover:opacity-75" href="https://www.facebook.com/MusicAll/  ">
          <i class="pi pi-facebook text-base! leading-normal!" />
        </a>
        <a class="cursor-pointer text-surface-900 dark:text-surface-0 hover:opacity-75">
          <i class="pi pi-envelope text-base! leading-normal!" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
