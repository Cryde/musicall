<template>
  <section class="relative overflow-hidden rounded-3xl mb-12 bg-gradient-to-br from-surface-950 via-primary-950/50 to-surface-950 dark:from-surface-950 dark:via-primary-950/50 dark:to-surface-950">
    <!-- Light mode background -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary-100 via-purple-50 to-primary-100 dark:hidden" />

    <!-- Gradient orbs background -->
    <div class="absolute top-20 left-1/4 w-72 h-72 bg-primary-600/30 dark:bg-primary-600/30 rounded-full blur-[100px] animate-drift" />
    <div class="absolute bottom-20 right-1/4 w-96 h-96 bg-fuchsia-600/20 dark:bg-fuchsia-600/20 rounded-full blur-[120px] animate-drift" style="animation-delay: -5s;" />
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-cyan-500/15 dark:bg-cyan-500/15 rounded-full blur-[80px] animate-glow" />

    <!-- Floating particles -->
    <div class="absolute inset-0 overflow-hidden">
      <!-- Large glowing orbs -->
      <div class="absolute top-[15%] right-[20%] w-4 h-4 bg-primary-400 rounded-full animate-glow shadow-lg shadow-primary-400/50" />
      <div class="absolute top-[25%] right-[35%] w-3 h-3 bg-fuchsia-400 rounded-full animate-glow shadow-lg shadow-fuchsia-400/50" style="animation-delay: -1s;" />
      <div class="absolute top-[60%] right-[15%] w-5 h-5 bg-cyan-400 rounded-full animate-glow shadow-lg shadow-cyan-400/50" style="animation-delay: -2s;" />
      <div class="absolute top-[45%] right-[40%] w-2 h-2 bg-white rounded-full animate-glow" style="animation-delay: -0.5s;" />
      <div class="absolute top-[70%] right-[30%] w-3 h-3 bg-primary-300 rounded-full animate-glow shadow-lg shadow-primary-300/50" style="animation-delay: -3s;" />

      <!-- Medium particles -->
      <div class="absolute top-[20%] right-[25%] w-2 h-2 bg-white/60 rounded-full animate-particle" style="animation-delay: -1s;" />
      <div class="absolute top-[35%] right-[18%] w-1.5 h-1.5 bg-primary-300/80 rounded-full animate-particle" style="animation-delay: -3s;" />
      <div class="absolute top-[50%] right-[45%] w-2 h-2 bg-fuchsia-300/70 rounded-full animate-particle" style="animation-delay: -5s;" />
      <div class="absolute top-[65%] right-[22%] w-1.5 h-1.5 bg-cyan-300/80 rounded-full animate-particle" style="animation-delay: -2s;" />
      <div class="absolute top-[80%] right-[38%] w-2 h-2 bg-white/50 rounded-full animate-particle" style="animation-delay: -4s;" />

      <!-- Small star particles -->
      <div class="absolute top-[12%] right-[30%] w-1 h-1 bg-white rounded-full animate-particle-slow" />
      <div class="absolute top-[28%] right-[42%] w-1 h-1 bg-white/80 rounded-full animate-particle-slow" style="animation-delay: -2s;" />
      <div class="absolute top-[42%] right-[12%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -4s;" />
      <div class="absolute top-[55%] right-[50%] w-1 h-1 bg-white/70 rounded-full animate-particle-slow" style="animation-delay: -1s;" />
      <div class="absolute top-[68%] right-[8%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -3s;" />
      <div class="absolute top-[75%] right-[48%] w-1 h-1 bg-white/80 rounded-full animate-particle-slow" style="animation-delay: -5s;" />
      <div class="absolute top-[85%] right-[25%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -6s;" />
    </div>

    <!-- Content -->
    <div class="relative z-10 p-8 lg:p-16 xl:p-20">
      <div class="max-w-2xl">

        <!-- Heading -->
        <h1 class="text-4xl lg:text-5xl xl:text-6xl font-bold mb-8 leading-[1.05]">
          <span class="text-surface-900 dark:text-white">La communauté de</span>
          <br />
          <span class="bg-gradient-to-r from-primary-600 via-fuchsia-500 to-cyan-500 dark:from-primary-400 dark:via-fuchsia-400 dark:to-cyan-400 bg-clip-text text-transparent">passionné·e·s de musique</span>
        </h1>

        <!-- Description -->
        <p class="text-xl text-surface-600 dark:text-surface-400 mb-10 leading-relaxed">
            Partagez votre passion, découvrez des contenus et connectez-vous avec des milliers de passionné·e·s sur MusicAll.
        </p>

        <!-- Features as pill tags -->
          <div class="flex flex-wrap gap-3 mb-10">
            <router-link
              :to="{ name: 'app_search_musician' }"
              class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <i class="pi pi-users mr-2 text-fuchsia-600 dark:text-fuchsia-400"/>Trouver un musicien ou groupe
            </router-link>
            <router-link
              :to="{ name: 'app_publications' }"
              class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <i class="pi pi-book mr-2 text-amber-600 dark:text-amber-400"/>Publications
            </router-link>
            <router-link
              :to="{ name: 'app_course' }"
              class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <i class="pi pi-graduation-cap mr-2 text-emerald-600 dark:text-emerald-400"/>Cours
            </router-link>
            <router-link
              :to="{ name: 'app_publications_by_category', params: { slug: 'photos' } }"
              class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <i class="pi pi-images mr-2 text-cyan-600 dark:text-cyan-400"/>Photos de live
            </router-link>
            <router-link
              :to="{ name: 'app_forum_index' }"
              class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <i class="pi pi-comments mr-2 text-violet-600 dark:text-violet-400"/>Forum
            </router-link>
          </div>

        <!-- CTA Buttons -->
        <div class="flex flex-wrap gap-4">
          <router-link v-if="!userSecurityStore.isAuthenticated" :to="{ name: 'app_register' }">
            <Button
              label="S'inscrire gratuitement"
              icon="pi pi-arrow-right"
              iconPos="right"
              rounded
              size="large"
              class="!bg-gradient-to-r !from-[#6b97be] !to-[#4a7599] dark:!from-[#7ba7ce] dark:!to-[#5b87ae] hover:!from-[#5b87ae] hover:!to-[#3a6589] dark:hover:!from-[#8bb7de] dark:hover:!to-[#6b97be] !shadow-xl !shadow-[#5b87ae]/30 dark:!shadow-[#5b87ae]/40 hover:!shadow-[#5b87ae]/50 hover:!-translate-y-1 !transition-all !duration-300"
            />
          </router-link>
          <router-link :to="{ name: 'app_discover' }">
            <Button
              label="Découvrir"
              icon="pi pi-compass"
              rounded
              size="large"
              severity="secondary"
              outlined
              class="!bg-gradient-to-r !from-surface-700 !to-surface-800 dark:!bg-none dark:!bg-white/5 !backdrop-blur-sm !border-surface-600 dark:!border-white/20 !text-white dark:!text-surface-300 hover:!from-surface-600 hover:!to-surface-700 dark:hover:!bg-white/10 !transition-all !duration-300"
            />
          </router-link>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { useUserSecurityStore } from '../../store/user/security.js'

const userSecurityStore = useUserSecurityStore()
</script>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .animate-drift, .animate-glow, .animate-particle, .animate-particle-slow {
    animation: none;
  }
}
</style>
