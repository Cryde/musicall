<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-semibold">{{ forumStore.currentForum?.title }}</h1>
      <Button
        label="Nouveau sujet"
        icon="pi pi-plus"
        severity="info"
        @click="handleOpenAddTopicModal"
      />
    </div>

    <template v-if="forumStore.isLoading">
      <TopicListItemSkeleton v-for="i in 5" :key="i" />
    </template>

    <template v-else>
      <div v-if="forumStore.topicsTotalItems > TOPICS_PER_PAGE" class="flex justify-start mb-6">
        <Paginator
          :rows="TOPICS_PER_PAGE"
          :totalRecords="forumStore.topicsTotalItems"
          :first="(currentPage - 1) * TOPICS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>

      <Card class="mb-4">
        <template #content>
          <div v-if="forumStore.topics.length === 0" class="text-center py-8 text-surface-500">
            Aucun sujet dans ce forum pour le moment.
          </div>
          <div v-else>
            <TopicListItem
              v-for="topic in forumStore.topics"
              :key="topic.id"
              :topic="topic"
            />
          </div>
        </template>
      </Card>

      <div v-if="forumStore.topicsTotalItems > TOPICS_PER_PAGE" class="flex justify-start">
        <Paginator
          :rows="TOPICS_PER_PAGE"
          :totalRecords="forumStore.topicsTotalItems"
          :first="(currentPage - 1) * TOPICS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>
    </template>

    <AddTopicModal
      v-model:visible="showAddTopicModal"
      :forum-slug="forumSlug"
      @created="handleTopicCreated"
    />

    <AuthRequiredModal
      v-model:visible="showAuthModal"
      :message="authModalMessage"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Card from 'primevue/card'
import Paginator from 'primevue/paginator'
import { useTitle } from '@vueuse/core'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import TopicListItem from '../../components/Forum/TopicListItem.vue'
import TopicListItemSkeleton from '../../components/Forum/TopicListItemSkeleton.vue'
import AddTopicModal from '../../components/Forum/AddTopicModal.vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import Breadcrumb from '../Global/Breadcrumb.vue'

const TOPICS_PER_PAGE = 15

const route = useRoute()
const router = useRouter()
const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()

const showAddTopicModal = ref(false)
const showAuthModal = ref(false)
const authModalMessage = ref('')

const forumSlug = computed(() => route.params.slug)
const currentPage = computed(() => {
  const page = route.query.page
  return page ? parseInt(page, 10) : 1
})

const breadcrumbItems = computed(() => [
  { label: 'Forum', to: { name: 'app_forum_index' } },
  { label: forumStore.currentForum?.title || '...' }
])

useTitle(computed(() => `${forumStore.currentForum?.title || 'Forum'} - MusicAll`))

async function fetchData() {
  await forumStore.loadForum(forumSlug.value)
  await forumStore.loadTopics({ forumSlug: forumSlug.value, page: currentPage.value })
}

function handlePageChange(event) {
  const newPage = event.page + 1
  router.push({
    name: 'forum_topic_list',
    params: { slug: forumSlug.value },
    query: newPage === 1 ? {} : { page: newPage }
  })
}

function handleOpenAddTopicModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Vous devez vous connecter pour créer un nouveau sujet.'
    showAuthModal.value = true
    return
  }
  showAddTopicModal.value = true
}

async function handleTopicCreated(topic) {
  router.push({ name: 'forum_topic_item', params: { slug: topic.slug } })
}

watch(
  () => route.query.page,
  async () => {
    if (route.name === 'forum_topic_list') {
      await forumStore.loadTopics({ forumSlug: forumSlug.value, page: currentPage.value })
    }
  }
)

onMounted(async () => {
  await fetchData()
})

onUnmounted(() => {
  forumStore.clearForum()
})
</script>
