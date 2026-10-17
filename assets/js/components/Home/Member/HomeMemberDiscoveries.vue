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
        <router-link
          :to="{ name: 'app_publication_show', params: { slug: discovery.slug } }"
          class="flex items-center gap-3.5 rounded-xl p-2 hover:bg-surface-50 dark:hover:bg-surface-800/60"
        >
          <img
            :src="discovery.cover"
            alt=""
            loading="lazy"
            class="w-24 h-14 shrink-0 rounded-lg object-cover bg-surface-200 dark:bg-surface-800"
          />
          <span class="flex flex-col min-w-0 flex-1">
            <span class="text-sm font-semibold line-clamp-2 text-surface-900 dark:text-surface-0">{{ discovery.title }}</span>
            <i18n-t keypath="home.member.discoveries.by" tag="span" class="text-sm text-surface-600 dark:text-surface-300">
              <template #author>
                <UserName :username="discovery.author.username" :display-name="displayName(discovery.author)" variant="tooltip" />
              </template>
            </i18n-t>
          </span>
          <span class="shrink-0 pr-1 text-sm font-bold text-teal-700 dark:text-teal-300" :aria-label="t('home.member.discoveries.upvotes', discovery.upvotes ?? 0)">
            ▲ {{ discovery.upvotes ?? 0 }}
          </span>
        </router-link>
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.discoveries.empty') }}</p>
  </HomeMemberPanel>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import publicationsApi from '../../../api/publication/publications.js'
import { displayName } from '../../../helper/user/displayName.js'
import UserName from '../../User/UserName.vue'
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
