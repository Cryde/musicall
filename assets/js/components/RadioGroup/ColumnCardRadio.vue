<template>
  <div
    :key="title"
    @click="$emit('select-item')"
    class="shadow-sm rounded-2xl p-0 sm:p-2 cursor-pointer bg-surface-0 dark:bg-surface-900 border"
    :class="{'border-transparent': slug !== currentSelectedSlug,  'border-info shadow-md': slug === currentSelectedSlug}"
  >
    <img
      :src="imageSrc"
      :alt="title"
      class="w-full h-20 object-cover rounded-lg"
    />
    <div class="pt-2 md:pt-4 pb-2 flex flex-col items-center gap-4">
      <div class="flex flex-col gap-2 w-full">
        <div class="text-surface-900 dark:text-surface-0 text-xs md:text-base font-semibold text-center">
          {{ title }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps(['imageSrc', 'title', 'slug', 'currentSelectedSlug'])
defineEmits(['select-item'])
</script>
