<template>
  <div class="flex flex-col gap-20 py-12 overflow-hidden">
    <!-- Hero Section -->
    <section class="text-center relative">
      <!-- Decorative background elements -->
      <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary-200/30 dark:bg-primary-500/10 rounded-full blur-3xl" />
        <div class="absolute -top-12 -right-12 w-72 h-72 bg-pink-200/30 dark:bg-pink-500/10 rounded-full blur-3xl" />
      </div>

      <div class="inline-flex items-center gap-2 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-4 py-2 rounded-full text-sm font-medium mb-6">
        <i class="pi pi-sparkles" />
        <span>{{ t('home.discover.badge') }}</span>
      </div>

      <h1 class="text-4xl md:text-6xl font-extrabold mb-6">
        <i18n-t keypath="home.discover.heading" tag="span" class="text-surface-900 dark:text-surface-0">
          <template #brand>
            <span class="bg-gradient-to-r from-primary-500 via-pink-500 to-purple-500 bg-clip-text text-transparent">{{ t('home.discover.brand') }}</span>
          </template>
        </i18n-t>
      </h1>

      <i18n-t keypath="home.discover.intro" tag="p" class="text-xl text-surface-600 dark:text-surface-400 max-w-2xl mx-auto leading-relaxed">
        <template #community>
          <span class="font-semibold text-surface-700 dark:text-surface-300">{{ t('home.discover.intro_community') }}</span>
        </template>
      </i18n-t>
    </section>

    <!-- Band Space -->
    <div class="flex flex-col xl:flex-row gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center">
            <i class="pi pi-users text-2xl text-purple-600 dark:text-purple-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.discover.band_space.title') }}</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
          {{ t('home.discover.band_space.body') }}
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="grid sm:grid-cols-2 gap-4">
          <FeatureItem v-for="module in BAND_SPACE_MODULES" :key="module.key" :icon="module.icon">{{ module.label }}</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_band_space_presentation' }" class="inline-block mt-8">
          <Button :label="t('home.discover.band_space.cta')" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <HomeBandSpaceDemo />
      </div>
    </div>

    <!-- Application Android -->
    <BandSpaceMobileApp />

    <!-- Publications -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
            <i class="pi pi-book text-2xl text-blue-600 dark:text-blue-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.discover.publications.title') }}</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
          {{ t('home.discover.publications.body') }}
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>{{ t('home.discover.publications.articles') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.publications.courses') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.publications.discoveries') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.publications.news') }}</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_publications' }" class="inline-block mt-8">
          <Button :label="t('home.discover.publications.cta')" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <PublicationsMockup />
        </div>
      </div>
    </div>

    <!-- Annonces musiciens -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
            <i class="pi pi-megaphone text-2xl text-orange-600 dark:text-orange-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.discover.announces.title') }}</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
          {{ t('home.discover.announces.body') }}
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>{{ t('home.discover.announces.by_instrument') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.announces.bands_nearby') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.announces.location_filters') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.announces.direct_contact') }}</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_search_musician' }" class="inline-block mt-8">
          <Button :label="t('home.discover.announces.cta')" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-orange-500/20 to-red-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <AnnouncesMockup />
        </div>
      </div>
    </div>

    <!-- Forum -->
    <div class="flex flex-col-reverse xl:flex-row-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
            <i class="pi pi-comments text-2xl text-green-600 dark:text-green-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.discover.forum.title') }}</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
          {{ t('home.discover.forum.body') }}
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>{{ t('home.discover.forum.topics') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.forum.help') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.forum.introductions') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.forum.moderation') }}</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_forum_index' }" class="inline-block mt-8">
          <Button :label="t('home.discover.forum.cta')" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-green-500/20 to-teal-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <ForumMockup />
        </div>
      </div>
    </div>

    <!-- Galeries photos -->
    <div class="flex xl:flex-row flex-col-reverse gap-12 xl:gap-20 items-center">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
            <i class="pi pi-images text-2xl text-violet-600 dark:text-violet-400" />
          </div>
          <h2 class="text-3xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.discover.photos.title') }}</h2>
        </div>
        <p class="text-surface-600 dark:text-surface-400 text-lg md:text-xl leading-relaxed">
          {{ t('home.discover.photos.body') }}
        </p>
        <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8" />
        <div class="flex flex-col gap-4">
          <FeatureItem>{{ t('home.discover.photos.concerts') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.photos.events') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.photos.quality') }}</FeatureItem>
          <FeatureItem>{{ t('home.discover.photos.share') }}</FeatureItem>
        </div>
        <router-link :to="{ name: 'app_publications_by_category', params: { slug: 'photos' } }" class="inline-block mt-8">
          <Button :label="t('home.discover.photos.cta')" icon="pi pi-arrow-right" iconPos="right" />
        </router-link>
      </div>
      <div class="w-full xl:flex-1">
        <div class="relative">
          <div class="absolute inset-0 bg-gradient-to-br from-violet-500/20 to-pink-500/20 rounded-2xl blur-2xl -z-10 scale-95" />
          <PhotosMockup />
        </div>
      </div>
    </div>

    <!-- Messagerie -->
    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-8 lg:gap-12 items-center bg-surface-50 dark:bg-surface-800 rounded-2xl p-8 border border-surface-200 dark:border-surface-700">
      <div>
        <div class="w-14 h-14 rounded-xl bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center mb-5">
          <i class="pi pi-envelope text-2xl text-pink-600 dark:text-pink-400" />
        </div>
        <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-3">{{ t('home.discover.messages.title') }}</h2>
        <p class="text-surface-600 dark:text-surface-400 leading-7">
          {{ t('home.discover.messages.body') }}
        </p>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <FeatureItem>{{ t('home.discover.messages.private') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.from_announces') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.realtime') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.media') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.reactions') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.music_links') }}</FeatureItem>
      </div>
    </div>

    <!-- CTA Section -->
    <section v-if="!userSecurityStore.isAuthenticated" class="relative text-center py-16 px-8 rounded-3xl overflow-hidden">
      <!-- Gradient background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-500 via-pink-500 to-purple-600" />

      <!-- Decorative circles -->
      <div class="absolute top-0 left-0 w-64 h-64 bg-white/10 rounded-full -translate-x-1/2 -translate-y-1/2" />
      <div class="absolute bottom-0 right-0 w-48 h-48 bg-white/10 rounded-full translate-x-1/3 translate-y-1/3" />

      <div class="relative">
        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
          <i class="pi pi-heart-fill" />
          <span>{{ t('home.discover.cta.badge') }}</span>
        </div>

        <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">
          {{ t('home.discover.cta.title') }}
        </h2>
        <p class="text-white/90 text-lg mb-8 max-w-xl mx-auto">
          {{ t('home.discover.cta.body') }}
        </p>
        <router-link :to="{ name: 'app_register' }">
          <Button :label="t('home.discover.cta.button')" icon="pi pi-user-plus" size="large" severity="contrast" class="shadow-xl" />
        </router-link>
      </div>
    </section>

    <!-- Back to home -->
    <div class="text-center pb-8">
      <router-link :to="{ name: 'app_home' }">
        <Button :label="t('home.discover.back_home')" icon="pi pi-arrow-left" severity="secondary" outlined />
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import BandSpaceMobileApp from '../../components/BandSpace/Presentation/BandSpaceMobileApp.vue'
import FeatureItem from '../../components/Discover/FeatureItem.vue'
import AnnouncesMockup from '../../components/Discover/Mockup/AnnouncesMockup.vue'
import ForumMockup from '../../components/Discover/Mockup/ForumMockup.vue'
import PhotosMockup from '../../components/Discover/Mockup/PhotosMockup.vue'
import PublicationsMockup from '../../components/Discover/Mockup/PublicationsMockup.vue'
import HomeBandSpaceDemo from '../../components/Home/HomeBandSpaceDemo.vue'
import { BAND_SPACE_MODULES } from '../../constants/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const { t } = useI18n()
useTitle(() => t('home.discover.title'))

const userSecurityStore = useUserSecurityStore()
</script>
