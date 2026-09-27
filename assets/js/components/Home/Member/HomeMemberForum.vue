<template>
  <HomeMemberPanel title="Forum" link-label="Tout le forum" :link-to="{ name: 'app_forum_index' }">
    <div v-if="isLoading" class="flex flex-col gap-3" aria-busy="true">
      <div v-for="i in 3" :key="i" class="h-12 rounded-lg bg-surface-100 dark:bg-surface-800 animate-pulse" />
    </div>
    <ul v-else-if="topics.length > 0" class="m-0 p-0 list-none flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <li v-for="topic in topics" :key="topic.slug">
        <router-link
          :to="{ name: 'forum_topic_item', params: { slug: topic.slug } }"
          class="flex items-start justify-between gap-3 py-3 rounded-md hover:bg-surface-50 dark:hover:bg-surface-800/60"
        >
          <span class="flex flex-col gap-0.5 min-w-0">
            <span class="text-sm font-semibold truncate text-surface-900 dark:text-surface-0">{{ topic.title }}</span>
            <span class="text-sm text-surface-600 dark:text-surface-300">
              {{ topic.forum_title }} · {{ topic.replies }} réponse{{ topic.replies > 1 ? 's' : '' }}
            </span>
          </span>
          <span class="shrink-0 text-sm text-surface-600 dark:text-surface-300">{{ relativeDate(topic.last_activity_datetime, { showHours: false }) }}</span>
        </router-link>
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-surface-600 dark:text-surface-300">Le forum est encore calme.</p>
  </HomeMemberPanel>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import forumApi from '../../../api/forum/forum.js'
import relativeDate from '../../../helper/date/relative-date.js'
import HomeMemberPanel from './HomeMemberPanel.vue'

/** The topics that moved last across the forums (#1078). */
const isLoading = ref(true)
const topics = ref([])

onMounted(async () => {
  try {
    topics.value = await forumApi.getRecentTopics()
  } catch {
    topics.value = []
  } finally {
    isLoading.value = false
  }
})
</script>
