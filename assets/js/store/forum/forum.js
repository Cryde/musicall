import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import forumApi from '../../api/forum/forum.js'

export const useForumStore = defineStore('forum', () => {
  const categories = ref([])
  const currentForum = ref(null)
  const topics = ref([])
  const topicsTotalItems = ref(0)
  const currentTopic = ref(null)
  const posts = ref([])
  const postsTotalItems = ref(0)
  const isLoading = ref(false)

  async function loadCategories() {
    isLoading.value = true
    try {
      categories.value = await forumApi.getCategories()
    } finally {
      isLoading.value = false
    }
  }

  async function loadForum(slug) {
    isLoading.value = true
    try {
      currentForum.value = await forumApi.getForum(slug)
    } finally {
      isLoading.value = false
    }
  }

  async function loadTopics({ forumSlug, page = 1 }) {
    isLoading.value = true
    try {
      const data = await forumApi.getTopicsByForum({ forumSlug, page })
      topics.value = data.member
      topicsTotalItems.value = data.totalItems
    } finally {
      isLoading.value = false
    }
  }

  async function loadTopic(slug) {
    isLoading.value = true
    try {
      currentTopic.value = await forumApi.getTopic(slug)
    } finally {
      isLoading.value = false
    }
  }

  async function loadPosts({ topicSlug, page = 1 }) {
    isLoading.value = true
    try {
      const data = await forumApi.getPostsByTopic({ topicSlug, page })
      posts.value = data.member
      postsTotalItems.value = data.totalItems
    } finally {
      isLoading.value = false
    }
  }

  async function createTopic({ forumSlug, title, message }) {
    const topic = await forumApi.createTopic({
      forum: `/api/forum/${forumSlug}`,
      title,
      message
    })
    return topic
  }

  async function createPost({ topicSlug, content }) {
    const post = await forumApi.createPost({
      topic: `/api/forums/topics/${topicSlug}`,
      content
    })
    posts.value = [...posts.value, post]
    postsTotalItems.value++
    return post
  }

  function clearCategories() {
    categories.value = []
  }

  function clearForum() {
    currentForum.value = null
    topics.value = []
    topicsTotalItems.value = 0
  }

  function clearTopic() {
    currentTopic.value = null
    posts.value = []
    postsTotalItems.value = 0
  }

  function clear() {
    clearCategories()
    clearForum()
    clearTopic()
  }

  return {
    categories: readonly(categories),
    currentForum: readonly(currentForum),
    topics: readonly(topics),
    topicsTotalItems: readonly(topicsTotalItems),
    currentTopic: readonly(currentTopic),
    posts: readonly(posts),
    postsTotalItems: readonly(postsTotalItems),
    isLoading: readonly(isLoading),
    loadCategories,
    loadForum,
    loadTopics,
    loadTopic,
    loadPosts,
    createTopic,
    createPost,
    clearCategories,
    clearForum,
    clearTopic,
    clear
  }
})
