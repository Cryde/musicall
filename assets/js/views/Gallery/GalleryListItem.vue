<template>
  <RouterLink
    v-slot="{ href, navigate }"
    :to="{ name: 'app_gallery_show', params: { slug: slug } }"
    custom
  >
    <a
      @click="navigate"
      :href="href"
      class="h-auto md:h-[160px] p-1 md:p-2 bg-white dark:bg-surface-900 rounded-2xl shadow-sm flex flex-col md:flex-row gap-4 cursor-pointer"
    >
      <img
        :src="coverImage"
        :alt="title"
        class="h-[130px] md:h-auto md:w-3/12 rounded-lg object-cover"
      />
      <div class="p-2 md:p-4 flex flex-col gap-4 w-full md:w-9/12">
        <div class="flex flex-col gap-4 flex-1">
          <div class="self-stretch md:h-[51px] flex flex-col gap-2">
            <div class="self-stretch text-surface-900 dark:text-surface-0 text-lg font-medium leading-normal">
              {{ title }}
            </div>
            <div class="self-stretch text-surface-600 dark:text-surface-200 text-sm leading-normal flex items-center gap-2">
              <i class="pi pi-images text-xs" />
              <span>{{ imageCount }} photos</span>
            </div>
          </div>
        </div>

        <div class="text-surface-500 dark:text-surface-300 text-xs md:text-sm leading-normal">
          par {{ author.username }} {{ relativeDateFilter(date) }}

          <Tag severity="secondary" value="Photos" class="ml-3" />
        </div>
      </div>
    </a>
  </RouterLink>
</template>

<script setup>
import Tag from 'primevue/tag'
import relativeDateFilter from '../../helper/date/relative-date.js'

defineProps({
  slug: { type: String, required: true },
  coverImage: { type: String, required: true },
  title: { type: String, required: true },
  imageCount: { type: Number, default: 0 },
  author: { type: Object, required: true },
  date: { type: String, default: null }
})
</script>
