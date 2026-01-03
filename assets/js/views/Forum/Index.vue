<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <h1 class="text-2xl font-semibold mb-6">Liste des forums</h1>

    <template v-if="forumStore.isLoading">
      <ForumCategorySkeleton v-for="i in 3" :key="i" />
    </template>

    <template v-else>
      <div v-for="category in forumStore.categories" :key="category.id" class="mb-8">
        <h2 class="text-xl font-semibold mb-2">{{ category.title }}</h2>
        <p class="text-surface-500 dark:text-surface-400 mb-4">{{ category.description }}</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <router-link
            v-for="forum in category.forums"
            :key="forum.id"
            :to="{ name: 'forum_topic_list', params: { slug: forum.slug } }"
            class="block"
          >
            <Card class="h-full hover:shadow-lg transition-shadow cursor-pointer">
              <template #title>
                <span class="text-lg">{{ forum.title }}</span>
              </template>
              <template #content>
                <p class="text-surface-600 dark:text-surface-300 text-sm">
                  {{ forum.description }}
                </p>
              </template>
            </Card>
          </router-link>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import { useTitle } from '@vueuse/core'
import { onMounted, onUnmounted } from 'vue'
import { useForumStore } from '../../store/forum/forum.js'
import ForumCategorySkeleton from '../../components/Forum/ForumCategorySkeleton.vue'
import Breadcrumb from '../Global/Breadcrumb.vue'

useTitle('Forum - MusicAll')

const forumStore = useForumStore()

const breadcrumbItems = [
  { label: 'Forum' }
]

onMounted(async () => {
  await forumStore.loadCategories()
})

onUnmounted(() => {
  forumStore.clearCategories()
})
</script>
