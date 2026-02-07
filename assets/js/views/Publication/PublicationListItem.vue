<template>
  <div class="flex flex-row gap-2 items-center">
    <VoteButtonsList
      v-if="slug"
      :slug="slug"
      :upvotes="upvotes"
      :downvotes="downvotes"
      :user-vote="userVote"
    />
    <RouterLink
      v-slot="{ href, navigate }"
      :to="toRoute"
      custom
    >
    <a
      @click="navigate"
      :href="href"
      class="h-auto md:h-[160px] p-1 md:p-2 bg-white dark:bg-surface-900 rounded-2xl shadow-sm flex flex-col md:flex-row gap-4 cursor-pointer flex-1 min-w-0"
    >
      <img
        :src="cover"
        :alt="title"
        loading="lazy"
        class="h-[130px] md:h-auto md:w-3/12 rounded-lg object-cover"
      />
      <div class="p-2 md:p-4 flex flex-col gap-4 w-full md:w-9/12">
        <div class="flex flex-col gap-4 flex-1">
          <div class="self-stretch md:h-[51px] flex flex-col gap-2 overflow-hidden">
            <div class="self-stretch text-surface-900 dark:text-surface-0 text-lg font-medium leading-normal truncate">
              {{ title }}
            </div>
            <div class="self-stretch text-surface-600 dark:text-surface-200 text-sm leading-normal line-clamp-1">
              {{ description }}
            </div>
          </div>
        </div>

          <div
              class="text-surface-500 dark:text-surface-300 text-xs md:text-sm leading-normal">
              par {{ author.username }} {{ relativeDateFilter(date) }}

          <Tag
              severity="secondary"
              :value="category.title"
              class="ml-3"
          />
          </div>
      </div>
    </a>
    </RouterLink>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag'
import relativeDateFilter from '../../helper/date/relative-date.js'
import VoteButtonsList from '../../components/Publication/VoteButtonsList.vue'

defineProps({
  toRoute: { type: Object, required: true },
  cover: { type: String, default: null },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  category: { type: Object, required: true },
  author: { type: Object, required: true },
  date: { type: String, default: null },
  slug: { type: String, default: null },
  upvotes: { type: Number, default: 0 },
  downvotes: { type: Number, default: 0 },
  userVote: { type: Number, default: null },
})
</script>
