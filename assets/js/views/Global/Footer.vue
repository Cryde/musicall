<template>
  <footer class="border-t border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-950 px-6 md:px-12 lg:px-20 pt-16">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-[minmax(0,1.6fr)_repeat(5,minmax(0,1fr))] gap-x-8 gap-y-12">
      <div class="col-span-2 md:col-span-3 lg:col-span-1 flex flex-col gap-4 lg:pr-6">
        <RouterLink
          :to="{ name: 'app_home' }"
          class="self-start text-xl tracking-wide text-surface-900 dark:text-surface-0"
        >
          <span class="font-extrabold">MUSIC</span>ALL
        </RouterLink>
        <p class="m-0 text-sm leading-relaxed text-surface-600 dark:text-surface-400 max-w-sm">
          {{ t('footer.tagline') }}
        </p>
        <div class="flex gap-2 pt-1">
          <a
            v-for="social in socialLinks"
            :key="social.label"
            :href="social.href"
            :aria-label="social.label"
            target="_blank"
            rel="noopener noreferrer"
            :class="socialButtonClass"
          >
            <i :class="social.icon" aria-hidden="true" />
          </a>
          <RouterLink
            :to="{ name: 'app_contact' }"
            :aria-label="t('footer.contact_form')"
            :class="socialButtonClass"
          >
            <i class="pi pi-envelope" aria-hidden="true" />
          </RouterLink>
        </div>
      </div>

      <nav
        v-for="column in columns"
        :key="column.title"
        :aria-label="column.title"
        class="flex flex-col gap-3"
      >
        <span class="pb-1 text-[15px] font-bold text-surface-900 dark:text-surface-0">{{ column.title }}</span>
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
      </nav>
    </div>

    <div class="mt-16 py-6 border-t border-surface-200 dark:border-surface-800 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-surface-600 dark:text-surface-400">
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
import { useUserSecurityStore } from '../../store/user/security.js'

const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)

const linkClass = 'text-sm text-surface-600 dark:text-surface-400 hover:text-primary'
const actionLinkClass = 'text-sm font-semibold text-primary hover:underline'
const socialButtonClass =
  'size-11 flex items-center justify-center rounded-lg border border-surface-300 dark:border-surface-700 text-surface-700 dark:text-surface-300 hover:text-primary hover:border-primary'

const { t } = useI18n()

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
