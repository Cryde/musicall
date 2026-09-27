<template>
  <section
    v-if="announces.length > 0"
    class="flex flex-col md:flex-row md:items-center justify-between gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
    aria-labelledby="home-member-my-announces-title"
  >
    <div class="flex flex-col gap-1 min-w-0">
      <h2 id="home-member-my-announces-title" class="m-0 text-base font-bold text-surface-900 dark:text-surface-0">
        Mes annonces · {{ announces.length }} en ligne
      </h2>
      <ul class="m-0 p-0 list-none flex flex-wrap gap-x-4 gap-y-1 text-sm text-surface-700 dark:text-surface-300">
        <li v-for="announce in shown" :key="announce.id" class="truncate">
          {{ announceHeadline(announce) }}<template v-if="announce.location_name"> · {{ announce.location_name }}</template>
        </li>
        <li v-if="announces.length > shown.length">et {{ announces.length - shown.length }} autre{{ announces.length - shown.length > 1 ? 's' : '' }}</li>
      </ul>
    </div>
    <router-link
      :to="{ name: 'app_user_announces' }"
      class="inline-flex items-center gap-1.5 shrink-0 text-sm font-semibold text-primary hover:underline"
    >
      Gérer mes annonces
      <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
    </router-link>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import musicianAnnounceApi from '../../../api/announce/musician.js'
import { announceHeadline } from '../../../utils/homeSearch.js'

/**
 * The member's own announces (#1078), for a member who is searching: what they posted, and a way to
 * manage it. Nothing at all when they have posted none.
 */
const SHOWN = 2

const announces = ref([])
const shown = computed(() => announces.value.slice(0, SHOWN))

onMounted(async () => {
  try {
    announces.value = (await musicianAnnounceApi.getByCurrentUser()).member
  } catch {
    announces.value = []
  }
})
</script>
