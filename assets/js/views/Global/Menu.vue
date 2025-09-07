<template>
    <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-900">
      <div class="flex items-center gap-4">
        <div class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
          <img src="../../../image/logo.png" alt="Logo" class="h-5 w-auto"/>
        </div>
      </div>
      <a
          v-styleclass="{
                    selector: '@next',
                    enterFromClass: 'hidden',
                    enterActiveClass: 'animate-fadein',
                    leaveToClass: 'hidden',
                    leaveActiveClass: 'animate-fadeout',
                    hideOnOutsideClick: true
                }"
          class="cursor-pointer block lg:hidden text-surface-100"
      >
        <i class="pi pi-bars text-xl! leading-normal!"/>
      </a>

      <div
          class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full bg-surface-900 left-0 top-full z-10 shadow lg:shadow-none border lg:border-0 border-surface-800"
      >
        <div class="flex-1 flex items-start gap-4 px-6 lg:px-0 py-4 lg:py-0 flex-col lg:flex-row">

          <RouterLink
              v-for="(item, i) in navs"
              :key="i"
              :to="{name: item.to}"
              custom
              v-slot="{ isActive, href, navigate }"
          >
            <a
                v-bind="$attrs"
                :href="href"
                @click="navigate"
                :class="[
                            'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors duration-150 border w-full lg:w-auto',
                            isActive ? 'bg-surface-800 border-surface-700 text-white' : 'border-transparent hover:bg-surface-800 hover:border-surface-700 text-surface-400 hover:text-white'
                        ]"
            >
              <span class="font-medium">{{ item.label }}</span>
            </a>
          </RouterLink>
        </div>

      </div>
    </nav>

</template>
<script setup>
import {ref} from 'vue';

const navs = ref([
  {
    label: 'Home',
    to: 'app_home'
  },
  {
    label: 'Publications',
    to: 'app_publications'
  },
  {
    label: 'Cours',
    to: 'app_course'
  },
  {
    label: 'Recherche',
    to: 'app_search_musician'
  },
  {
    label: 'Forum',
    to: 'app_forum_index'
  }
]);

</script>
