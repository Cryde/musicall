<template>
  <div class="flex flex-col gap-10 pb-10 md:gap-20 md:py-12 overflow-hidden">
    <!-- Hero Section -->
    <section class="text-center relative">
      <!-- Decorative background elements -->
      <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary-200/30 dark:bg-primary-500/10 rounded-full blur-3xl" />
        <div class="absolute -top-12 -right-12 w-72 h-72 bg-pink-200/30 dark:bg-pink-500/10 rounded-full blur-3xl" />
      </div>

      <div class="inline-flex items-center gap-2 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-3 py-1.5 rounded-full text-[13px] font-medium mb-3 md:px-4 md:py-2 md:text-sm md:mb-6">
        <i class="pi pi-sparkles" />
        <span>{{ t('home.discover.badge') }}</span>
      </div>

      <h1 class="text-[34px] max-md:leading-[1.1] md:text-6xl font-extrabold mb-3 md:mb-6">
        <i18n-t keypath="home.discover.heading" tag="span" class="text-surface-900 dark:text-surface-0">
          <template #brand>
            <span class="bg-gradient-to-r from-primary-500 via-pink-500 to-purple-500 bg-clip-text text-transparent">{{ t('home.discover.brand') }}</span>
          </template>
        </i18n-t>
      </h1>

      <i18n-t keypath="home.discover.intro" tag="p" class="text-[17px] text-surface-600 dark:text-surface-400 max-w-[30ch] mx-auto leading-normal md:text-xl md:max-w-2xl md:leading-relaxed">
        <template #community>
          <span class="font-semibold text-surface-700 dark:text-surface-300">{{ t('home.discover.intro_community') }}</span>
        </template>
      </i18n-t>
    </section>

    <!-- Band Space: the demo's tabs already name every module, so a phone skips the list of them. -->
    <DiscoverSection
      icon="pi pi-users"
      icon-class="bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400"
      :title="t('home.discover.band_space.title')"
      :body="t('home.discover.band_space.body')"
      :cta-label="t('home.discover.band_space.cta')"
      :cta-to="{ name: 'app_band_space_presentation' }"
    >
      <template #features>
        <div class="grid sm:grid-cols-2 gap-4 max-md:hidden">
          <FeatureItem v-for="module in BAND_SPACE_MODULES" :key="module.key" :icon="module.icon">{{ module.label }}</FeatureItem>
        </div>
      </template>
      <HomeBandSpaceDemo />
    </DiscoverSection>

    <!-- Application mobile -->
    <BandSpaceMobileApp />

    <DiscoverSection
      icon="pi pi-book"
      icon-class="bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400"
      :title="t('home.discover.publications.title')"
      :body="t('home.discover.publications.body')"
      :cta-label="t('home.discover.publications.cta')"
      :cta-to="{ name: 'app_publications' }"
      glow-class="from-blue-500/20 to-purple-500/20"
      reverse
    :features="[
        t('home.discover.publications.articles'),
        t('home.discover.publications.courses'),
        t('home.discover.publications.discoveries'),
        t('home.discover.publications.news')
      ]"
    >
      <PublicationsMockup />
    </DiscoverSection>

    <DiscoverSection
      icon="pi pi-megaphone"
      icon-class="bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400"
      :title="t('home.discover.announces.title')"
      :body="t('home.discover.announces.body')"
      :cta-label="t('home.discover.announces.cta')"
      :cta-to="{ name: 'app_search_musician' }"
      glow-class="from-orange-500/20 to-red-500/20"
    :features="[
        t('home.discover.announces.by_instrument'),
        t('home.discover.announces.bands_nearby'),
        t('home.discover.announces.location_filters'),
        t('home.discover.announces.direct_contact')
      ]"
    >
      <AnnouncesMockup />
    </DiscoverSection>

    <DiscoverSection
      icon="pi pi-comments"
      icon-class="bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400"
      :title="t('home.discover.forum.title')"
      :body="t('home.discover.forum.body')"
      :cta-label="t('home.discover.forum.cta')"
      :cta-to="{ name: 'app_forum_index' }"
      glow-class="from-green-500/20 to-teal-500/20"
      reverse
    :features="[
        t('home.discover.forum.topics'),
        t('home.discover.forum.help'),
        t('home.discover.forum.introductions'),
        t('home.discover.forum.moderation')
      ]"
    >
      <ForumMockup />
    </DiscoverSection>

    <DiscoverSection
      icon="pi pi-images"
      icon-class="bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400"
      :title="t('home.discover.photos.title')"
      :body="t('home.discover.photos.body')"
      :cta-label="t('home.discover.photos.cta')"
      :cta-to="{ name: 'app_publications_by_category', params: { slug: 'photos' } }"
      glow-class="from-violet-500/20 to-pink-500/20"
    :features="[
        t('home.discover.photos.concerts'),
        t('home.discover.photos.events'),
        t('home.discover.photos.quality'),
        t('home.discover.photos.share')
      ]"
    >
      <PhotosMockup />
    </DiscoverSection>

    <!-- Messagerie -->
    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-3 md:gap-8 lg:gap-12 items-center bg-surface-50 dark:bg-surface-800 rounded-[20px] md:rounded-2xl p-[18px] md:p-8 border border-surface-200 dark:border-surface-700">
      <div>
        <!-- The icon beside the heading on a phone, above it from md. -->
        <div class="flex items-center gap-3 mb-3 md:block md:mb-0">
          <div class="w-10 h-10 md:w-14 md:h-14 shrink-0 rounded-xl bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center md:mb-5">
            <i class="pi pi-envelope text-lg md:text-2xl text-pink-600 dark:text-pink-400" />
          </div>
          <h2 class="text-[22px] md:text-2xl font-bold text-surface-900 dark:text-surface-0 md:mb-3">{{ t('home.discover.messages.title') }}</h2>
        </div>
        <p class="text-[15px] leading-[1.6] md:text-base md:leading-7 text-surface-600 dark:text-surface-400">
          {{ t('home.discover.messages.body') }}
        </p>
      </div>
      <div class="grid sm:grid-cols-2 gap-2 md:gap-4">
        <FeatureItem>{{ t('home.discover.messages.private') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.from_announces') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.realtime') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.media') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.reactions') }}</FeatureItem>
        <FeatureItem>{{ t('home.discover.messages.music_links') }}</FeatureItem>
      </div>
    </div>

    <!-- CTA Section -->
    <section v-if="!userSecurityStore.isAuthenticated" class="relative text-center py-8 px-5 md:py-16 md:px-8 rounded-3xl overflow-hidden">
      <!-- Gradient background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-500 via-pink-500 to-purple-600" />

      <!-- Decorative circles -->
      <div class="absolute top-0 left-0 w-64 h-64 bg-white/10 rounded-full -translate-x-1/2 -translate-y-1/2" />
      <div class="absolute bottom-0 right-0 w-48 h-48 bg-white/10 rounded-full translate-x-1/3 translate-y-1/3" />

      <div class="relative">
        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-3 md:mb-6">
          <i class="pi pi-heart-fill" />
          <span>{{ t('home.discover.cta.badge') }}</span>
        </div>

        <h2 class="text-[26px] max-md:leading-tight md:text-4xl font-extrabold text-white mb-3 md:mb-4">
          {{ t('home.discover.cta.title') }}
        </h2>
        <p class="text-white/90 text-base mb-5 md:text-lg md:mb-8 max-w-xl mx-auto">
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
import DiscoverSection from '../../components/Discover/DiscoverSection.vue'
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
