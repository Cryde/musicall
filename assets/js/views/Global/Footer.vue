<template>
  <footer class="border-t border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-950 px-6 md:px-12 lg:px-20 pt-8 sm:pt-16">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-[minmax(0,1.6fr)_repeat(5,minmax(0,1fr))] gap-x-8 sm:gap-y-12">
      <div class="sm:col-span-2 md:col-span-3 lg:col-span-1 flex flex-col gap-3 sm:gap-4 lg:pr-6 max-sm:pb-6 max-sm:border-b max-sm:border-surface-200 dark:max-sm:border-surface-800">
        <!-- On a phone the wordmark and the social links share a row. From sm the row dissolves into the
             column, and the order classes put the links back under the tagline. -->
        <div class="flex items-center justify-between gap-3 sm:contents">
          <RouterLink
            :to="{ name: 'app_home' }"
            class="self-start max-sm:self-center text-xl tracking-wide text-surface-900 dark:text-surface-0"
          >
            <span class="font-extrabold">MUSIC</span>ALL
          </RouterLink>
          <div class="flex gap-2 sm:order-1 sm:pt-1">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              :aria-label="social.label"
              target="_blank"
              rel="noopener noreferrer"
              :class="[socialButtonClass, 'max-sm:size-10']"
            >
              <i :class="social.icon" aria-hidden="true" />
            </a>
            <RouterLink
              :to="{ name: 'app_contact' }"
              :aria-label="t('footer.contact_form')"
              :class="[socialButtonClass, 'max-sm:size-10']"
            >
              <i class="pi pi-envelope" aria-hidden="true" />
            </RouterLink>
          </div>
        </div>
        <p class="m-0 text-sm leading-relaxed text-surface-600 dark:text-surface-400 max-w-sm">
          {{ t('footer.tagline') }}
        </p>
        <GooglePlayBadge v-if="offersApp" placement="footer" class="self-start h-10 sm:order-2" />
      </div>

      <nav
        v-for="(column, index) in columns"
        :key="column.title"
        :aria-label="column.title"
        class="flex flex-col gap-3 max-sm:gap-0 max-sm:border-b max-sm:border-surface-200 dark:max-sm:border-surface-800"
      >
        <!-- On a phone each group folds behind its title: unfolded, the links were a screen long. -->
        <button
          v-if="isPhone"
          type="button"
          class="flex h-12 w-full cursor-pointer items-center justify-between border-0 bg-transparent p-0 text-[15px] font-bold text-surface-900 dark:text-surface-0"
          :aria-expanded="openColumns.has(index)"
          :aria-controls="`footer-links-${index}`"
          @click="toggleColumn(index)"
        >
          {{ column.title }}
          <i
            class="pi pi-chevron-down text-xs text-surface-500 dark:text-surface-400 transition-transform duration-200"
            :class="{ 'rotate-180': openColumns.has(index) }"
            aria-hidden="true"
          />
        </button>
        <span v-else class="pb-1 text-[15px] font-bold text-surface-900 dark:text-surface-0">{{ column.title }}</span>
        <div
          v-show="!isPhone || openColumns.has(index)"
          :id="`footer-links-${index}`"
          class="flex flex-col gap-3 max-sm:pb-4"
        >
          <RouterLink
            v-for="link in column.links"
            :key="link.label"
            :to="link.to"
            :class="link.isAction ? actionLinkClass : linkClass"
          >
            {{ link.label }}
          </RouterLink>
          <button
            v-if="column.hasPostAnnounce"
            type="button"
            :class="[actionLinkClass, 'self-start cursor-pointer']"
            @click="handlePostAnnounce"
          >
            {{ t('footer.post_announce') }}
          </button>
        </div>
      </nav>
    </div>

    <div class="sm:mt-16 py-6 sm:border-t border-surface-200 dark:border-surface-800 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-surface-600 dark:text-surface-400">
      <span>{{ t('footer.copyright', { year: currentYear }) }}</span>
      <RouterLink
        v-for="link in legalLinks"
        :key="link.label"
        :to="link.to"
        class="hover:text-primary"
      >
        {{ link.label }}
      </RouterLink>
    </div>

    <AddAnnounceModal v-if="showAnnounceModal" v-model:visible="showAnnounceModal" />
    <AuthRequiredModal v-if="showAuthModal" v-model:visible="showAuthModal" variant="post_announce" />
  </footer>
</template>

<script setup>
import { computed, defineAsyncComponent, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GooglePlayBadge from '../../components/MobileApp/GooglePlayBadge.vue'
import { useIsPhone } from '../../composables/useIsPhone.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { offersMobileApp } from '../../utils/mobileApp.js'

const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)

const linkClass = 'text-sm text-surface-600 dark:text-surface-400 hover:text-primary'
const actionLinkClass = 'text-sm font-semibold text-primary hover:underline'
const socialButtonClass =
  'size-11 flex items-center justify-center rounded-lg border border-surface-300 dark:border-surface-700 text-surface-700 dark:text-surface-300 hover:text-primary hover:border-primary'

const { t } = useI18n()
const isPhone = useIsPhone()

// The link groups unfolded on a phone, by position: each folds on its own.
const openColumns = ref(new Set())

function toggleColumn(index) {
  const next = new Set(openColumns.value)
  if (next.has(index)) next.delete(index)
  else next.add(index)
  openColumns.value = next
}

const currentYear = new Date().getFullYear()

// Computed, like the lists below, so the labels follow a locale change.
const socialLinks = computed(() => [
  {
    label: t('footer.follow_instagram'),
    href: 'https://www.instagram.com/music.all.official',
    icon: 'pi pi-instagram'
  },
  {
    label: t('footer.follow_facebook'),
    href: 'https://www.facebook.com/MusicAll/',
    icon: 'pi pi-facebook'
  }
])

const bandSpacePresentation = { name: 'app_band_space_presentation' }

// Opens the demo on /band-space on that module's tab.
function bandSpaceModule(module) {
  return { ...bandSpacePresentation, query: { module } }
}

function publicationCategory(slug) {
  return { name: 'app_publications_by_category', params: { slug } }
}

function courseCategory(slug) {
  return { name: 'app_course_by_category', params: { slug } }
}

const columns = computed(() => [
  {
    title: t('footer.search.title'),
    hasPostAnnounce: true,
    links: [
      { label: t('footer.search.guitarist'), to: { name: 'app_search_guitarist' } },
      { label: t('footer.search.drummer'), to: { name: 'app_search_drummer' } },
      { label: t('footer.search.bassist'), to: { name: 'app_search_bassist' } },
      { label: t('footer.search.singer'), to: { name: 'app_search_singer' } },
      { label: t('footer.search.pianist'), to: { name: 'app_search_pianist' } }
    ]
  },
  {
    title: t('footer.band_space.title'),
    links: [
      { label: t('footer.band_space.presentation'), to: bandSpacePresentation },
      { label: t('footer.band_space.agenda'), to: bandSpaceModule('agenda') },
      { label: t('footer.band_space.chat'), to: bandSpaceModule('chat') },
      { label: t('footer.band_space.setlists'), to: bandSpaceModule('setlists') },
      { label: t('footer.band_space.finances'), to: bandSpaceModule('finances') },
      // app_band_index sends a visitor to the presentation and a member to their space.
      { label: t('footer.band_space.create'), to: { name: 'app_band_index' }, isAction: true }
    ]
  },
  {
    title: t('footer.publications.title'),
    links: [
      { label: t('footer.publications.news'), to: publicationCategory('news') },
      { label: t('footer.publications.reviews'), to: publicationCategory('chroniques') },
      { label: t('footer.publications.interviews'), to: publicationCategory('interviews') },
      { label: t('footer.publications.live_reports'), to: publicationCategory('live-reports') },
      { label: t('footer.publications.articles'), to: publicationCategory('articles') },
      { label: t('footer.publications.photos'), to: publicationCategory('photos') }
    ]
  },
  {
    title: t('footer.courses.title'),
    links: [
      { label: t('footer.courses.guitar'), to: courseCategory('guitare') },
      { label: t('footer.courses.bass'), to: courseCategory('basse') },
      { label: t('footer.courses.drums'), to: courseCategory('batterie') },
      { label: t('footer.courses.computer_music'), to: courseCategory('mao') },
      { label: t('footer.courses.misc'), to: courseCategory('divers') }
    ]
  },
  {
    title: t('footer.community.title'),
    links: [
      { label: t('footer.community.discover'), to: { name: 'app_discover' } },
      { label: t('footer.community.forum'), to: { name: 'app_forum_index' } },
      { label: t('footer.community.contact'), to: { name: 'app_contact' } }
    ]
  }
])

const legalLinks = computed(() => [
  { label: t('footer.legal.privacy'), to: { name: 'app_privacy' } },
  { label: t('footer.legal.terms'), to: { name: 'app_terms' } },
  { label: t('footer.legal.legal_notice'), to: { name: 'app_mentions_legales' } },
  { label: t('footer.legal.delete_account'), to: { name: 'app_account_deletion' } }
])

const userSecurityStore = useUserSecurityStore()
const offersApp = computed(() =>
  offersMobileApp(userSecurityStore.userProfile, globalThis.navigator)
)
const showAnnounceModal = ref(false)
const showAuthModal = ref(false)

function handlePostAnnounce() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  showAnnounceModal.value = true
}
</script>
