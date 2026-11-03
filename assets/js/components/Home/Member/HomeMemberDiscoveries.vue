<template>
  <HomeMemberPanel
    :title="t('home.member.discoveries.title')"
    :link-label="t('home.member.discoveries.link')"
    :link-to="{ name: 'app_publications_by_category', params: { slug: DISCOVERIES_SLUG } }"
  >
    <div v-if="isLoading" class="flex flex-col gap-3" aria-busy="true">
      <div v-for="i in DISCOVERIES_SHOWN" :key="i" class="h-16 rounded-lg bg-surface-100 dark:bg-surface-800 animate-pulse" />
    </div>
    <ul v-else-if="discoveries.length > 0" class="m-0 p-0 list-none flex flex-col gap-2">
      <li v-for="discovery in discoveries" :key="discovery.id">
        <PublicationRow
          :to="{ name: 'app_publication_show', params: { slug: discovery.slug } }"
          :cover="discovery.cover"
          :title="discovery.title"
          :author="discovery.author"
          class="rounded-xl p-2 hover:bg-surface-50 dark:hover:bg-surface-800/60"
        >
          <template #aside>
            <span class="shrink-0 pr-1 text-sm tabular-nums text-surface-600 dark:text-surface-300" :aria-label="t('home.member.discoveries.upvotes', discovery.upvotes ?? 0)">
              ▲ {{ discovery.upvotes ?? 0 }}
            </span>
          </template>
        </PublicationRow>
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.discoveries.empty') }}</p>
  </HomeMemberPanel>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import publicationsApi from '../../../api/publication/publications.js'
import PublicationRow from '../../Publication/PublicationRow.vue'
import HomeMemberPanel from './HomeMemberPanel.vue'

/** The videos the community shared last (#1078). */
const DISCOVERIES_SLUG = 'decouvertes'
const DISCOVERIES_SHOWN = 4

const { t } = useI18n()
const isLoading = ref(true)
const discoveries = ref([])

onMounted(async () => {
  try {
    const data = await publicationsApi.getPublications({ page: 1, slug: DISCOVERIES_SLUG })
    discoveries.value = data.member.slice(0, DISCOVERIES_SHOWN)
  } catch {
    discoveries.value = []
  } finally {
    isLoading.value = false
  }
})
</script>
