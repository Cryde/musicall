<template>
  <div class="bg-transparent dark:bg-surface-950">
    <ul class="list-none m-0 bg-transparent dark:bg-surface-950 flex items-center gap-2 font-medium overflow-x-auto">
      <li>
        <router-link
          :to="{ name: 'app_home' }"
          class="cursor-pointer transition-all"
        >
          <i class="pi pi-home text-base! leading-normal! text-surface-500 dark:text-surface-300"/>
        </router-link>
      </li>
      <template
        v-for="(item, i) in items"
        :key="i"
      >
        <li>
          <i class="pi pi-angle-right text-base! leading-normal! text-surface-500 dark:text-surface-300"/>
        </li>
        <li>
          <router-link
            v-if="item.to"
            :to="item.to"
            :class="['cursor-pointer whitespace-nowrap transition-all text-surface-500 dark:text-surface-400 hover:text-primary-500 dark:hover:text-primary-400']"
          >
            {{ item.label }}
          </router-link>
          <span
            v-else
            :class="['whitespace-nowrap text-surface-500 dark:text-surface-400']"
          >
            {{ item.label }}
          </span>
        </li>
      </template>
    </ul>
  </div>
</template>

<script setup>
defineProps(['items'])
</script>
