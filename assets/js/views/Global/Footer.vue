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
          La plateforme gratuite des musicien·ne·s et des groupes : trouver, organiser, apprendre. Depuis 2008.
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
            aria-label="Accéder au formulaire de contact"
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
          Poster une annonce
        </button>
      </nav>
    </div>

    <div class="mt-16 py-6 border-t border-surface-200 dark:border-surface-800 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-surface-600 dark:text-surface-400">
      <span>© 2008-{{ currentYear }} MusicAll</span>
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
import { defineAsyncComponent, ref } from 'vue'
import { useUserSecurityStore } from '../../store/user/security.js'

const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)

const linkClass = 'text-sm text-surface-600 dark:text-surface-400 hover:text-primary'
const actionLinkClass = 'text-sm font-semibold text-primary hover:underline'
const socialButtonClass =
  'size-11 flex items-center justify-center rounded-lg border border-surface-300 dark:border-surface-700 text-surface-700 dark:text-surface-300 hover:text-primary hover:border-primary'

const currentYear = new Date().getFullYear()

const socialLinks = [
  {
    label: 'Suivre MusicAll sur Instagram',
    href: 'https://www.instagram.com/music.all.official',
    icon: 'pi pi-instagram'
  },
  {
    label: 'Suivre MusicAll sur Facebook',
    href: 'https://www.facebook.com/MusicAll/',
    icon: 'pi pi-facebook'
  }
]

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

const columns = [
  {
    title: 'Recherche',
    hasPostAnnounce: true,
    links: [
      { label: 'Un·e guitariste', to: { name: 'app_search_guitarist' } },
      { label: 'Un·e batteur·euse', to: { name: 'app_search_drummer' } },
      { label: 'Un·e bassiste', to: { name: 'app_search_bassist' } },
      { label: 'Un·e chanteur·euse', to: { name: 'app_search_singer' } },
      { label: 'Un·e pianiste', to: { name: 'app_search_pianist' } }
    ]
  },
  {
    title: 'Band Space',
    links: [
      { label: 'Présentation', to: bandSpacePresentation },
      { label: 'Agenda', to: bandSpaceModule('agenda') },
      { label: 'Setlists', to: bandSpaceModule('setlists') },
      { label: 'Finances du groupe', to: bandSpaceModule('finances') },
      // app_band_index sends a visitor to the presentation and a member to their space.
      { label: 'Créer un Band Space', to: { name: 'app_band_index' }, isAction: true }
    ]
  },
  {
    title: 'Publications',
    links: [
      { label: 'News', to: publicationCategory('news') },
      { label: 'Chroniques', to: publicationCategory('chroniques') },
      { label: 'Interviews', to: publicationCategory('interviews') },
      { label: 'Live-reports', to: publicationCategory('live-reports') },
      { label: 'Articles', to: publicationCategory('articles') },
      { label: 'Photos', to: publicationCategory('photos') }
    ]
  },
  {
    title: 'Cours',
    links: [
      { label: 'Guitare', to: courseCategory('guitare') },
      { label: 'Basse', to: courseCategory('basse') },
      { label: 'Batterie', to: courseCategory('batterie') },
      { label: 'MAO', to: courseCategory('mao') },
      { label: 'Divers', to: courseCategory('divers') }
    ]
  },
  {
    title: 'Communauté',
    links: [
      { label: 'Forum', to: { name: 'app_forum_index' } },
      { label: 'Nous contacter', to: { name: 'app_contact' } }
    ]
  }
]

const legalLinks = [
  { label: 'Confidentialité', to: { name: 'app_privacy' } },
  { label: 'CGU', to: { name: 'app_terms' } },
  { label: 'Mentions légales', to: { name: 'app_mentions_legales' } }
]

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
