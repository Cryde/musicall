<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex items-center gap-3 mb-6">
      <h1 class="text-2xl font-semibold">{{ forumStore.currentTopic?.title }}</h1>
      <Tag v-if="forumStore.currentTopic?.is_locked" value="Verrouillé" severity="warn" icon="pi pi-lock" />
    </div>

    <template v-if="forumStore.isLoading && !forumStore.currentTopic">
      <TopicPostSkeleton v-for="i in 3" :key="i" />
    </template>

    <template v-else>
      <div v-if="forumStore.postsTotalItems > POSTS_PER_PAGE" class="flex justify-start mb-6">
        <Paginator
          :rows="POSTS_PER_PAGE"
          :totalRecords="forumStore.postsTotalItems"
          :first="(currentPage - 1) * POSTS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>

      <TopicPost
        v-for="post in forumStore.posts"
        :key="post.id"
        :post="post"
      />

      <div v-if="forumStore.postsTotalItems > POSTS_PER_PAGE" class="flex justify-start my-6">
        <Paginator
          :rows="POSTS_PER_PAGE"
          :totalRecords="forumStore.postsTotalItems"
          :first="(currentPage - 1) * POSTS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>

      <Divider />

      <div class="max-w-3xl mx-auto mt-6">
        <h2 class="text-xl font-semibold mb-4">Poster un message sur ce sujet</h2>
        <AddMessageForm
          v-if="forumStore.currentTopic"
          :topic-slug="forumStore.currentTopic.slug"
          :is-locked="forumStore.currentTopic.is_locked"
          @message-created="handleMessageCreated"
        />
      </div>
    </template>

    <AuthRequiredModal
      v-model:visible="showAuthModal"
      :message="authModalMessage"
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Divider from 'primevue/divider'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import AddMessageForm from '../../components/Forum/AddMessageForm.vue'
import TopicPost from '../../components/Forum/TopicPost.vue'
import TopicPostSkeleton from '../../components/Forum/TopicPostSkeleton.vue'
import { useForumStore } from '../../store/forum/forum.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const POSTS_PER_PAGE = 10

const route = useRoute()
const router = useRouter()
const forumStore = useForumStore()

const showAuthModal = ref(false)
const authModalMessage = ref('')

const topicSlug = computed(() => route.params.slug)
const currentPage = computed(() => {
  const page = route.params.page
  return page ? Number.parseInt(page, 10) : 1
})

const breadcrumbItems = computed(() => [
  { label: 'Forum', to: { name: 'app_forum_index' } },
  {
    label: forumStore.currentTopic?.forum?.title || '...',
    to: forumStore.currentTopic?.forum?.slug
      ? { name: 'forum_topic_list', params: { slug: forumStore.currentTopic.forum.slug } }
      : null
  },
  { label: forumStore.currentTopic?.title || '...' }
])

useTitle(computed(() => `${forumStore.currentTopic?.title || 'Sujet'} - MusicAll`))

async function fetchData() {
  await forumStore.loadTopic(topicSlug.value)
  trackUmamiEvent('forum-topic-view')
  await forumStore.loadPosts({ topicSlug: topicSlug.value, page: currentPage.value })
  await scrollToHash()
}

async function scrollToHash() {
  if (route.hash) {
    await nextTick()
    const element = document.querySelector(route.hash)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' })
    }
  }
}

function handlePageChange(event) {
  const newPage = event.page + 1
  router.push({
    name: 'forum_topic_item',
    params: newPage === 1 ? { slug: topicSlug.value } : { slug: topicSlug.value, page: newPage }
  })
}

async function handleMessageCreated(postId) {
  // Reload posts to show the new message
  const totalPages = Math.ceil((forumStore.postsTotalItems + 1) / POSTS_PER_PAGE)
  const hash = `#post-${postId}`

  if (totalPages > currentPage.value) {
    // Navigate to last page to see the new post
    router.push({
      name: 'forum_topic_item',
      params: { slug: topicSlug.value, page: totalPages },
      hash
    })
  } else {
    await forumStore.loadPosts({ topicSlug: topicSlug.value, page: currentPage.value })
    await nextTick()
    const element = document.querySelector(hash)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' })
    }
  }
}

watch(
  () => route.params.page,
  async () => {
    if (route.name === 'forum_topic_item') {
      await forumStore.loadPosts({ topicSlug: topicSlug.value, page: currentPage.value })
      await scrollToHash()
    }
  }
)

onMounted(async () => {
  await fetchData()
})

onUnmounted(() => {
  forumStore.clearTopic()
})
</script>
