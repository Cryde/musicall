<template>
  <section class="relative overflow-hidden rounded-3xl bg-surface-950">
    <!-- Light mode background -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary-100 via-purple-50 to-primary-100 dark:hidden" aria-hidden="true" />

    <!-- Gradient orbs and a few particles, as the rest of the site's hero cards -->
    <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
      <div class="absolute top-10 left-[10%] w-72 h-72 bg-cyan-500/10 rounded-full blur-[100px]" />
      <div class="absolute bottom-0 right-[20%] w-96 h-96 bg-fuchsia-600/20 rounded-full blur-[120px] animate-drift" />
      <div class="absolute top-[12%] right-[14%] w-3.5 h-3.5 bg-cyan-400 rounded-full animate-glow shadow-lg shadow-cyan-400/50" />
      <div class="absolute top-[82%] left-[52%] w-2 h-2 bg-fuchsia-400 rounded-full animate-glow" style="animation-delay: -1s;" />
      <div class="absolute top-[88%] right-[8%] w-1 h-1 bg-white rounded-full animate-particle-slow" />
    </div>

    <div class="relative z-10 grid lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-12 items-center p-6 sm:p-10 lg:p-16 xl:p-20">
      <div class="flex flex-col gap-6">
        <h1 class="m-0 text-4xl sm:text-5xl xl:text-6xl font-extrabold leading-[1.06]">
          <span class="text-surface-900 dark:text-white">Trouvez les musiciens</span>
          <br />
          <span class="text-brand-gradient">qui manquent à votre groupe.</span>
        </h1>
        <p class="m-0 text-lg lg:text-xl leading-relaxed text-surface-700 dark:text-surface-300 max-w-2xl">
          Annonces de musiciens et de groupes, un espace privé pour gérer votre groupe, des cours et une
          communauté de passionné·e·s.
        </p>

        <HomeSearchForm />

        <nav v-if="frequentLinks.length > 0" class="flex flex-wrap items-center gap-2" aria-label="Recherches fréquentes">
          <span class="text-sm text-surface-700 dark:text-surface-300">Recherches fréquentes :</span>
          <router-link
            v-for="link in frequentLinks"
            :key="link.label"
            :to="link.route"
            class="rounded-full border border-surface-300 dark:border-surface-600 bg-surface-0/70 dark:bg-surface-800/70 px-3 py-1 text-sm text-surface-800 dark:text-surface-100 hover:bg-surface-0 dark:hover:bg-surface-700"
          >
            {{ link.label }}
          </router-link>
        </nav>
      </div>

      <!-- A glimpse of the latest announces. Decorative here, since the same announces are listed,
           reachable, right below: inert keeps their links out of the tab order. -->
      <div
        v-if="previewAnnounces.length > 0"
        class="relative hidden lg:block h-[28rem]"
        aria-hidden="true"
        inert
      >
        <div
          v-for="(announce, index) in previewAnnounces"
          :key="announce.id"
          class="absolute w-[26rem] max-w-full shadow-2xl shadow-surface-950/30 rounded-2xl"
          :class="STACK_POSITIONS[index]"
        >
          <HomeAnnounceCard :announce="announce" compact />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { frequentSearchLink } from '../../utils/homeSearch.js'
import HomeAnnounceCard from './HomeAnnounceCard.vue'
import HomeSearchForm from './HomeSearchForm.vue'

const props = defineProps({
  /** Up to three announces, oldest at the back. */
  previewAnnounces: { type: Array, default: () => [] },
  /** What enough different people searched for lately (#1075); the row stays out when there is none. */
  frequentSearches: { type: Array, default: () => [] }
})

const frequentLinks = computed(() => props.frequentSearches.map(frequentSearchLink))

const STACK_POSITIONS = [
  // Dimmed rather than faded: a translucent card would show the text of the one behind it.
  'left-10 top-0 -rotate-2 dark:brightness-[0.6]',
  'left-2 top-32 rotate-[1.5deg] dark:brightness-[0.8]',
  'left-8 top-64'
]
</script>
