<template>
  <div :class="containerClass">
    <div class="flex-1">
      <div class="flex items-center gap-2 mb-1">
        <Tag v-if="isPinned" value="Epinglé" severity="info" icon="pi pi-thumbtack" class="text-xs" />
        <Tag v-if="topic.is_locked" value="Verrouillé" severity="warn" icon="pi pi-lock" class="text-xs" />
      </div>
      <router-link
        :to="{ name: 'forum_topic_item', params: { slug: topic.slug } }"
        class="text-lg font-medium text-primary hover:underline"
      >
        {{ topic.title }}
      </router-link>
      <div class="text-sm text-surface-500 dark:text-surface-400 mt-1">
        Par
        <router-link
          v-if="!topic.author.deletion_datetime"
          :to="{ name: 'app_user_public_profile', params: { username: topic.author.username } }"
          class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
        >{{ authorName }}</router-link>
        <span v-else class="font-semibold text-surface-500">{{ authorName }}</span>
        le {{ formatDate(topic.creation_datetime) }}
      </div>
    </div>

    <div class="flex items-center gap-2 text-surface-600 dark:text-surface-300 shrink-0">
      <i class="pi pi-comments" />
      <span>{{ topic.post_number }}</span>
    </div>

    <div
      v-if="topic.last_post"
      class="text-sm text-surface-500 dark:text-surface-400 md:w-48 shrink-0"
    >
      <div>
        Dernier message par
        <router-link
          v-if="!topic.last_post.creator.deletion_datetime"
          :to="{ name: 'app_user_public_profile', params: { username: topic.last_post.creator.username } }"
          class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
          @click.stop
        >{{ lastPostCreatorName }}</router-link>
        <span v-else class="font-semibold text-surface-500">{{ lastPostCreatorName }}</span>
      </div>
      <router-link :to="lastPostRoute" class="hover:text-primary transition-colors">
        le {{ formatDate(topic.last_post.creation_datetime) }}
      </router-link>
    </div>
    <div v-else class="text-sm text-surface-400 dark:text-surface-500 md:w-48 shrink-0">
      Aucune réponse
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { displayName } from '../../helper/user/displayName.js'
import { formatDate } from '../../utils/date.js'

const POSTS_PER_PAGE = 10

const props = defineProps({
  topic: {
    type: Object,
    required: true
  }
})

const isPinned = computed(() => props.topic.type === 1)
const authorName = computed(() => displayName(props.topic.author))
const lastPostCreatorName = computed(() => props.topic.last_post ? displayName(props.topic.last_post.creator) : '')

const containerClass = computed(() => [
  'flex flex-col md:flex-row md:items-center py-4 border-b border-surface-200 dark:border-surface-700 last:border-b-0 gap-2 md:gap-4',
  isPinned.value ? 'bg-primary-50 dark:bg-primary-900/10 -mx-4 px-4 rounded-lg' : ''
])

const lastPostRoute = computed(() => {
  const lastPage = Math.ceil(props.topic.post_number / POSTS_PER_PAGE)
  const hash = `#post-${props.topic.last_post.id}`
  if (lastPage <= 1) {
    return { name: 'forum_topic_item', params: { slug: props.topic.slug }, hash }
  }
  return { name: 'forum_topic_item', params: { slug: props.topic.slug, page: lastPage }, hash }
})
</script>
