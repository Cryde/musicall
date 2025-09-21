<template>
    <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-0 dark:bg-surface-900">
      <div class="flex items-center gap-4">
        <div class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
          <img src="../../../image/logo.png" alt="Logo" class="h-4 w-auto"/>
        </div>
      </div>
      <span
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
      </span>

      <div
          class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full dark:bg-surface-900 left-0 top-full z-10 shadow lg:shadow-none border lg:border-0 border-surface-800"
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
                            isActive
                            ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
                            : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
                        ]"
            >
              <span class="font-medium">{{ item.label }}</span>
            </a>
          </RouterLink>
        </div>

      </div>
      <div>
        <Button
            :icon="iconClass"
            size="small"
            severity="secondary"
            outlined
            class="text-sm! leading-normal! w-9 h-9 p-0! shrink-0 rounded-md"
            @click="switchDarkMode"
        />
      </div>
    </nav>
</template>
<script setup>
import {ref} from 'vue';
import * as Cookies from 'es-cookie';

const isDarkMode = ref(Cookies.get('is_dark_mode') === '1');
const iconClass = ref('');

if (isDarkMode.value) {
  iconClass.value = 'pi pi-sun'
} else {
  iconClass.value = 'pi pi-moon'
}

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

function switchDarkMode() {
  const html = document.querySelector('html');
  if (html.classList.contains('dark-mode')) {
    Cookies.set('is_dark_mode', 0);
    html.classList.remove('dark-mode');
    isDarkMode.value = false;
    iconClass.value = 'pi pi-moon'
  } else {
    html.classList.add('dark-mode');
    Cookies.set('is_dark_mode', 1);
    isDarkMode.value = true;
    iconClass.value = 'pi pi-sun'
  }
}
</script>
