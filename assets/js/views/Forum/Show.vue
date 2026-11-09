<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-semibold">{{ forumStore.currentForum?.title }}</h1>
      <Button
        :label="t('forum.show.new_topic')"
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
            {{ t('forum.show.no_topics') }}
          </div>
          <FadeList v-else>
            <TopicListItem
              v-for="topic in forumStore.topics"
              :key="topic.id"
              :topic="topic"
            />
          </FadeList>
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
      :initial-title="resumedTopic?.title ?? ''"
      :initial-message="resumedTopic?.html ?? ''"
      @created="handleTopicCreated"
      @sign-in-required="handleTopicSignInRequired"
    />

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
import Card from 'primevue/card'
import Paginator from 'primevue/paginator'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import AddTopicModal from '../../components/Forum/AddTopicModal.vue'
import TopicListItem from '../../components/Forum/TopicListItem.vue'
import TopicListItemSkeleton from '../../components/Forum/TopicListItemSkeleton.vue'
import FadeList from '../../components/Global/FadeList.vue'
import { useContentLanguage } from '../../composables/useContentLanguage.js'
import { useContentPageLoad } from '../../composables/useContentPageLoad.js'
import { useKeepDraft } from '../../composables/useKeepDraft.js'
import { usePendingActionResumer } from '../../composables/usePendingAction.js'
import { useForumStore } from '../../store/forum/forum.js'
import {
  FORUM_TOPIC_CREATE,
  readTopicDraft,
  topicCreateAvailability,
  topicDraftPayload
} from '../../utils/forumDraft.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const TOPICS_PER_PAGE = 15

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const forumStore = useForumStore()
const moveToContentLanguage = useContentLanguage()
const pageLoad = useContentPageLoad()
const keepDraft = useKeepDraft()

const showAddTopicModal = ref(false)
// The topic a visitor wrote before signing in (#1305), while the dialog reopened on it.
const resumedTopic = ref(null)
const showAuthModal = ref(false)
const authModalMessage = ref('')

const forumSlug = computed(() => route.params.slug)
const currentPage = computed(() => {
  const page = route.query.page
  return page ? Number.parseInt(page, 10) : 1
})

const breadcrumbItems = computed(() => [
  { label: t('forum.common.forum'), to: { name: 'app_forum_index' } },
  { label: forumStore.currentForum?.title || '...' }
])

useTitle(
  computed(() =>
    t('forum.common.page_title', {
      title: forumStore.currentForum?.title || t('forum.common.forum')
    })
  )
)

/** @returns {Promise<boolean>} whether it moved the page under the forum's language */
async function fetchData() {
  const loadedPath = route.path
  await forumStore.loadForum(forumSlug.value)
  if (moveToContentLanguage(forumStore.currentForum?.locale, loadedPath)) {
    return true
  }
  await forumStore.loadTopics({ forumSlug: forumSlug.value, page: currentPage.value })
  return false
}

function handlePageChange(event) {
  const newPage = event.page + 1
  router.push({
    name: 'forum_topic_list',
    params: { slug: forumSlug.value },
    query: newPage === 1 ? {} : { page: newPage }
  })
}

// A visitor writes too (#1305): only « Créer » asks them to sign in.
function handleOpenAddTopicModal() {
  trackUmamiEvent('forum-new-topic-click', { forum: forumSlug.value })
  resumedTopic.value = null
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
  await pageLoad.track(fetchData())
})

onUnmounted(() => {
  forumStore.clearForum()
})

// --- A topic written before signing in (#1305): reopened prefilled, never published on its own ------------

function handleTopicSignInRequired({ title, html, reason }) {
  const payload = topicDraftPayload({ forumSlug: forumSlug.value, title, html })
  const isKept = keepDraft(FORUM_TOPIC_CREATE, payload)
  // Promised only when kept: a browser that blocks storage gets the usual prompt.
  authModalMessage.value = isKept
    ? t(reason === 'image' ? 'forum.pending.image_sign_in' : 'forum.pending.topic_sign_in')
    : t('forum.show.login_to_create')
  showAuthModal.value = true
}

usePendingActionResumer(FORUM_TOPIC_CREATE, async (payload) => {
  const draft = readTopicDraft(payload)
  const isForumThere = await pageLoad.whenLoaded()
  const availability = topicCreateAvailability({
    forum: isForumThere ? forumStore.currentForum : null,
    forumSlug: draft?.forumSlug,
    pageForumSlug: forumSlug.value
  })
  if (availability !== 'available') {
    return { unavailable: t('forum.pending.topic_forum_gone') }
  }

  return {
    label: t('forum.pending.topic_label'),
    resume: () => {
      resumedTopic.value = draft
      showAddTopicModal.value = true
    }
  }
})
</script>
