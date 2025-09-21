<template>
  <div class="bg-transparent dark:bg-surface-950">
    <ul class="list-none m-0 bg-transparent dark:bg-surface-950 flex items-center gap-2 font-medium overflow-x-auto">
      <li>
        <a class="cursor-pointer transition-all">
          <i class="pi pi-home text-base! leading-normal! text-surface-500 dark:text-surface-300"/>
        </a>
      </li>
      <template v-for="(item, i) in items" :key="i">
        <li>
          <i class="pi pi-angle-right text-base! leading-normal! text-surface-500 dark:text-surface-300"/>
        </li>
        <li>
          <a :class="['cursor-pointer whitespace-nowrap  transition-all text-surface-500 dark:text-surface-400']">
            {{ item.label }}
          </a>
        </li>
      </template>
    </ul>
  </div>
</template>
<script setup>
defineProps(['items'])
</script>