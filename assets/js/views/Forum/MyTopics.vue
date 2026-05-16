<template>
  <div>
    <div class="flex justify-end mb-6">
      <Breadcrumb :items="breadcrumbItems" />
    </div>

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Mes sujets</h1>
    </div>

    <div v-if="store.isLoading && !store.participations.length" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-20 bg-surface-100 dark:bg-surface-800 animate-pulse rounded" />
    </div>

    <div v-else-if="!store.participations.length" class="text-surface-500 dark:text-surface-400 py-8 text-center">
      Vous ne participez à aucun sujet pour le moment.
    </div>

    <div v-else>
      <div v-if="store.totalItems > ITEMS_PER_PAGE" class="flex justify-start mb-6">
        <Paginator
          :rows="ITEMS_PER_PAGE"
          :totalRecords="store.totalItems"
          :first="(currentPage - 1) * ITEMS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>

      <ul class="divide-y divide-surface-200 dark:divide-surface-700">
        <li
          v-for="item in store.participations"
          :key="item.id"
          class="py-4 flex items-center gap-3"
        >
          <Tag
            v-if="!item.is_read"
            value="Non lu"
            severity="info"
            icon="pi pi-circle-fill"
            class="text-xs shrink-0"
          />
          <Tag
            v-if="item.topic.is_pinned"
            value="Épinglé"
            severity="info"
            icon="pi pi-thumbtack"
            class="text-xs shrink-0"
          />
          <Tag
            v-if="item.topic.is_resolved"
            value="Résolu"
            severity="success"
            icon="pi pi-check-circle"
            class="text-xs shrink-0"
          />
          <Tag
            v-if="item.topic.is_locked"
            value="Verrouillé"
            severity="warn"
            icon="pi pi-lock"
            class="text-xs shrink-0"
          />
          <div class="flex-1 min-w-0">
            <router-link
              :to="{ name: 'forum_topic_item', params: { slug: item.topic.slug } }"
              class="text-base font-medium text-primary hover:underline"
            >{{ item.topic.title }}</router-link>
            <div class="text-sm text-surface-500 dark:text-surface-400 mt-1">
              Dans
              <router-link
                :to="{ name: 'forum_topic_list', params: { slug: item.topic.forum.slug } }"
                class="hover:text-primary transition-colors"
              >{{ item.topic.forum.title }}</router-link>
              ·
              <span v-if="item.topic.last_post">
                Dernier message le {{ formatDate(item.topic.last_post.creation_datetime) }}
              </span>
              <span v-else>Aucun message</span>
            </div>
          </div>
          <Button
            v-tooltip.left="'Retirer de mes sujets'"
            icon="pi pi-times"
            size="small"
            severity="secondary"
            text
            rounded
            :loading="removingId === item.id"
            aria-label="Retirer de mes sujets"
            @click="handleRemove(item.id)"
          />
        </li>
      </ul>

      <div v-if="store.totalItems > ITEMS_PER_PAGE" class="flex justify-start mt-6">
        <Paginator
          :rows="ITEMS_PER_PAGE"
          :totalRecords="store.totalItems"
          :first="(currentPage - 1) * ITEMS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useForumParticipationsStore } from '../../store/forum/participations.js'
import { formatDate } from '../../utils/date.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const ITEMS_PER_PAGE = 15

const route = useRoute()
const router = useRouter()
const store = useForumParticipationsStore()
const toast = useToast()

const removingId = ref(null)

const currentPage = computed(() => {
  const page = route.query.page
  return page ? Number.parseInt(page, 10) : 1
})

const breadcrumbItems = computed(() => [
  { label: 'Forum', to: { name: 'app_forum_index' } },
  { label: 'Mes sujets' }
])

useTitle('Mes sujets - MusicAll')

async function handleRemove(id) {
  if (removingId.value) return
  removingId.value = id
  try {
    await store.removeParticipation(id)
    toast.add({ severity: 'success', summary: 'Sujet retiré de votre liste', life: 3000 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    removingId.value = null
  }
}

function handlePageChange(event) {
  const newPage = event.page + 1
  router.push({
    name: 'app_forum_my_topics',
    query: newPage === 1 ? {} : { page: newPage }
  })
}

watch(currentPage, async (page) => {
  await store.loadParticipations({ page })
})

onMounted(async () => {
  await store.loadParticipations({ page: currentPage.value })
})

onUnmounted(() => {
  store.clear()
})
</script>
