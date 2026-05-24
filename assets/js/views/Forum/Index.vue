<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <router-link
      v-if="userSecurityStore.isAuthenticated"
      :to="{ name: 'app_forum_my_topics' }"
      class="inline-flex items-center gap-2 mb-4 text-sm text-primary hover:underline"
    >
      <i class="pi pi-bookmark" />
      Mes sujets
    </router-link>

    <h1 class="text-2xl font-semibold mb-6">Liste des forums</h1>

    <IconField class="mb-8 w-full max-w-xl">
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="searchInput"
        placeholder="Rechercher un sujet ou un message…"
        class="w-full"
      />
      <button
        v-if="searchInput"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600"
        aria-label="Effacer la recherche"
        @click="clearSearch"
      >
        <i class="pi pi-times" />
      </button>
    </IconField>

    <ForumSearchResults
      v-if="hasActiveSearch"
      :term="committedTerm"
      :results="searchResults"
      :total-items="searchTotalItems"
      :page="searchPage"
      :items-per-page="searchItemsPerPage"
      :is-loading="isSearching"
      :error="searchError"
      @page-change="goToPage"
    />

    <template v-else>
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
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Card from 'primevue/card'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import forumApi from '../../api/forum/forum.js'
import ForumCategorySkeleton from '../../components/Forum/ForumCategorySkeleton.vue'
import ForumSearchResults from '../../components/Forum/ForumSearchResults.vue'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

useTitle('Forum - MusicAll')

const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()

const breadcrumbItems = [{ label: 'Forum' }]

const searchInput = ref('')
const committedTerm = ref('')
const searchResults = ref([])
const searchTotalItems = ref(0)
const searchPage = ref(1)
const searchItemsPerPage = ref(20)
const isSearching = ref(false)
const searchError = ref(null)

const MIN_TERM_LENGTH = 3
const DEBOUNCE_MS = 300

const hasActiveSearch = computed(() => committedTerm.value !== '')

let debounceTimer = null
let lastRequestId = 0

watch(searchInput, (value) => {
  const trimmed = (value ?? '').trim()
  clearTimeout(debounceTimer)
  if (trimmed === '') {
    committedTerm.value = ''
    searchResults.value = []
    searchTotalItems.value = 0
    searchError.value = null
    return
  }
  if (trimmed.length < MIN_TERM_LENGTH) {
    return
  }
  debounceTimer = setTimeout(() => runSearch(trimmed, 1), DEBOUNCE_MS)
})

async function runSearch(term, page) {
  const requestId = ++lastRequestId
  isSearching.value = true
  searchError.value = null
  committedTerm.value = term
  searchPage.value = page
  try {
    const data = await forumApi.search({ term, page })
    if (requestId !== lastRequestId) return
    searchResults.value = data.member
    searchTotalItems.value = data.totalItems
  } catch (error) {
    if (requestId !== lastRequestId) return
    searchError.value = error?.response?.data?.detail ?? 'Erreur pendant la recherche'
    searchResults.value = []
    searchTotalItems.value = 0
  } finally {
    if (requestId === lastRequestId) {
      isSearching.value = false
    }
  }
}

function goToPage(page) {
  runSearch(committedTerm.value, page)
}

function clearSearch() {
  searchInput.value = ''
}

onMounted(async () => {
  await forumStore.loadCategories()
})

onUnmounted(() => {
  forumStore.clearCategories()
  clearTimeout(debounceTimer)
})
</script>
