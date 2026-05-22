<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex items-center gap-3 mb-6">
      <h1 class="text-2xl font-semibold">{{ forumStore.currentTopic?.title }}</h1>
      <Tag v-if="forumStore.currentTopic?.is_pinned" value="Épinglé" severity="info" icon="pi pi-thumbtack" />
      <Tag v-if="forumStore.currentTopic?.is_resolved" value="Résolu" severity="success" icon="pi pi-check-circle" />
      <Tag v-if="forumStore.currentTopic?.is_locked" value="Verrouillé" severity="warn" icon="pi pi-lock" />
      <Button
        v-if="canManageTopic"
        :label="forumStore.currentTopic.is_resolved ? 'Marquer non résolu' : 'Marquer résolu'"
        :icon="forumStore.currentTopic.is_resolved ? 'pi pi-times-circle' : 'pi pi-check-circle'"
        size="small"
        severity="secondary"
        text
        :loading="isResolveLoading"
        @click="handleToggleResolved"
      />
      <Button
        v-if="canManageTopic"
        :label="forumStore.currentTopic.is_locked ? 'Déverrouiller' : 'Verrouiller'"
        :icon="forumStore.currentTopic.is_locked ? 'pi pi-lock-open' : 'pi pi-lock'"
        size="small"
        severity="secondary"
        text
        :loading="isToggleLoading"
        @click="handleToggleLock"
      />
      <Button
        v-if="userSecurityStore.isAdmin && forumStore.currentTopic"
        :label="forumStore.currentTopic.is_pinned ? 'Détacher' : 'Épingler'"
        icon="pi pi-thumbtack"
        size="small"
        severity="secondary"
        text
        :loading="isPinLoading"
        @click="handleTogglePinned"
      />
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
        :is-locked="forumStore.currentTopic?.is_locked ?? false"
        @quote="handleQuote"
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

      <div ref="replyFormSectionRef" class="max-w-3xl mx-auto mt-6">
        <h2 class="text-xl font-semibold mb-4">Poster un message sur ce sujet</h2>
        <AddMessageForm
          v-if="forumStore.currentTopic"
          ref="addMessageFormRef"
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
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import AddMessageForm from '../../components/Forum/AddMessageForm.vue'
import TopicPost from '../../components/Forum/TopicPost.vue'
import TopicPostSkeleton from '../../components/Forum/TopicPostSkeleton.vue'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const POSTS_PER_PAGE = 10

const route = useRoute()
const router = useRouter()
const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()
const toast = useToast()

const showAuthModal = ref(false)
const authModalMessage = ref('')
const isToggleLoading = ref(false)
const isResolveLoading = ref(false)
const isPinLoading = ref(false)
const addMessageFormRef = ref(null)
const replyFormSectionRef = ref(null)

function handleQuote(quote) {
  addMessageFormRef.value?.insertQuote(quote)
  nextTick(() => {
    replyFormSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}

const canManageTopic = computed(() => {
  const topic = forumStore.currentTopic
  if (!topic || !userSecurityStore.isAuthenticated) return false
  if (userSecurityStore.isAdmin) return true
  return topic.author?.id === userSecurityStore.userProfile?.id
})

async function handleToggleLock() {
  if (!forumStore.currentTopic) return
  isToggleLoading.value = true
  try {
    if (forumStore.currentTopic.is_locked) {
      await forumStore.unlockCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet déverrouillé', life: 3000 })
    } else {
      await forumStore.lockCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet verrouillé', life: 3000 })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isToggleLoading.value = false
  }
}

async function handleTogglePinned() {
  if (!forumStore.currentTopic) return
  isPinLoading.value = true
  try {
    if (forumStore.currentTopic.is_pinned) {
      await forumStore.unpinCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet détaché', life: 3000 })
    } else {
      await forumStore.pinCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet épinglé', life: 3000 })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isPinLoading.value = false
  }
}

async function handleToggleResolved() {
  if (!forumStore.currentTopic) return
  isResolveLoading.value = true
  try {
    if (forumStore.currentTopic.is_resolved) {
      await forumStore.unresolveCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet marqué non résolu', life: 3000 })
    } else {
      await forumStore.resolveCurrentTopic()
      toast.add({ severity: 'success', summary: 'Sujet marqué résolu', life: 3000 })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isResolveLoading.value = false
  }
}

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
  trackUmamiEvent('forum-topic-view', { topic: topicSlug.value })
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
