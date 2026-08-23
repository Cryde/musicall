<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="max-w-4xl mx-auto py-8 lg:py-16">
      <!-- Hero -->
      <div class="flex flex-col items-center text-center">
        <i class="pi pi-users !text-6xl text-primary mb-6" aria-hidden="true" />
        <h1 class="text-3xl lg:text-4xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Répétitions, concerts, morceaux : tout le groupe au même endroit
        </h1>
        <p class="text-surface-600 dark:text-surface-300 text-lg max-w-2xl mb-8">
          Un Band Space réunit l'agenda, les tâches, les notes, les setlists, les fichiers et les
          finances de votre groupe dans un espace partagé. Fini l'info retrouvée à la main dans la
          conversation de groupe, trois jours après la répét.
        </p>

        <div class="flex flex-col sm:flex-row items-center gap-3 mb-4">
          <Button
            label="Créer un Band Space"
            icon="pi pi-plus"
            size="large"
            @click="goToRegister"
          />
          <Button
            label="J'ai déjà un compte"
            severity="secondary"
            outlined
            size="large"
            @click="goToLogin"
          />
        </div>
        <p class="text-sm text-surface-500 dark:text-surface-400">
          Gratuit, et compris dans votre compte MusicAll.
        </p>
      </div>

      <!-- Module tour. Tabs rather than one section per module: all six labels stay visible at once,
           the screenshot is big enough to read, and only the selected one is ever downloaded. -->
      <div class="mt-16">
        <div
          class="flex flex-wrap justify-center gap-2 mb-6"
          role="tablist"
          aria-label="Modules du Band Space"
        >
          <button
            v-for="module in BAND_SPACE_MODULES"
            :id="`module-tab-${module.key}`"
            :key="module.key"
            role="tab"
            :aria-selected="module.key === activeModuleKey"
            :aria-controls="`module-panel-${module.key}`"
            :tabindex="module.key === activeModuleKey ? 0 : -1"
            :class="[
              'flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-colors cursor-pointer',
              module.key === activeModuleKey
                ? 'bg-primary text-white'
                : 'bg-surface-0 dark:bg-surface-900 text-surface-700 dark:text-surface-200 hover:bg-surface-200 dark:hover:bg-surface-700'
            ]"
            @click="activeModuleKey = module.key"
            @keydown.left.prevent="moveByArrow(-1)"
            @keydown.right.prevent="moveByArrow(1)"
            @keydown.home.prevent="moveToEdge('first')"
            @keydown.end.prevent="moveToEdge('last')"
          >
            <i :class="module.icon" aria-hidden="true" />
            {{ module.label }}
          </button>
        </div>

        <!-- Every panel stays mounted so each tab's aria-controls always points at a real element,
             which is why this uses v-show. The image itself keeps a v-if scoped to the active module,
             so only one <img> exists and the browser still fetches a single screenshot. -->
        <div
          v-for="module in BAND_SPACE_MODULES"
          v-show="module.key === activeModuleKey"
          :id="`module-panel-${module.key}`"
          :key="module.key"
          role="tabpanel"
          :aria-labelledby="`module-tab-${module.key}`"
        >
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 text-center mb-2">
            {{ module.label }}
          </h2>
          <p class="text-surface-600 dark:text-surface-300 text-center max-w-2xl mx-auto mb-6">
            {{ module.description }}
          </p>
          <!-- Two files per module, because dark mode is a deliberate choice here rather than a media
               query, and a light screenshot inside a dark page reads as broken. -->
          <img
            v-if="module.key === activeModuleKey"
            :src="(isDarkMode ? darkShots : lightShots)[module.key]"
            :alt="`Le module ${module.label} d'un Band Space`"
            width="1440"
            height="900"
            loading="lazy"
            class="w-full h-auto rounded-2xl shadow-2xl border border-surface-200 dark:border-surface-700"
          />
          <p class="text-xs text-surface-500 dark:text-surface-400 text-center mt-3">
            Capture d'un Band Space de démonstration.
          </p>
        </div>
      </div>

      <!-- Plenty of musicians play in more than one band, and the switcher is a real differentiator
           against a per-band group chat, which cannot separate anything. -->
      <div class="mt-16 flex flex-col sm:flex-row items-start gap-5 p-6 lg:p-8 bg-surface-0 dark:bg-surface-900 rounded-xl">
        <i class="pi pi-sync !text-3xl text-primary shrink-0" aria-hidden="true" />
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
            Vous jouez dans plusieurs groupes ?
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Créez un Band Space par groupe et passez de l'un à l'autre en un clic. Chaque espace garde
            son agenda, ses fichiers, ses setlists et ses comptes, sans jamais les mélanger. Vos
            groupes ne voient que le leur.
          </p>
        </div>
      </div>

      <!-- What it replaces. The honest comparison is what bands use today, not another product. -->
      <div class="mt-16 p-6 lg:p-8 bg-surface-100 dark:bg-surface-800 rounded-xl">
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-5">
          Ce que ça remplace
        </h2>
        <ul class="flex flex-col gap-4">
          <li v-for="item in replacements" :key="item.before" class="flex items-start gap-3">
            <i class="pi pi-arrow-right text-primary mt-1 shrink-0" aria-hidden="true" />
            <p class="text-surface-700 dark:text-surface-200">
              <span class="text-surface-500 dark:text-surface-400">{{ item.before }}</span>
              {{ item.after }}
            </p>
          </li>
        </ul>
      </div>

      <!-- Invitations, shown rather than described: the roster is what a visitor is really buying. -->
      <div class="mt-16 grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
            Invitez votre groupe par mail
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Chaque membre reçoit un lien, rejoint l'espace et voit tout de suite l'agenda, les
            fichiers et les setlists. Vous choisissez qui est administrateur, et vous pouvez retirer
            un membre quand quelqu'un quitte le groupe.
          </p>
        </div>
        <img
          :src="isDarkMode ? darkMembres : lightMembres"
          alt="La liste des membres d'un Band Space"
          width="1440"
          height="900"
          loading="lazy"
          class="w-full h-auto rounded-2xl shadow-2xl border border-surface-200 dark:border-surface-700"
        />
      </div>

      <!-- Closing call to action, for anyone who scrolled instead of clicking above -->
      <div class="flex flex-col items-center text-center mt-16">
        <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0 mb-3">
          Prêt à organiser votre groupe ?
        </h2>
        <p class="text-surface-600 dark:text-surface-300 mb-6 max-w-xl">
          Créez votre espace, invitez les autres membres par mail, et travaillez à plusieurs dès la
          prochaine répét.
        </p>
        <Button label="Créer un Band Space" icon="pi pi-plus" size="large" @click="goToRegister" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import darkAgenda from '../../../image/band-space/dark-agenda.webp'
import darkFiles from '../../../image/band-space/dark-files.webp'
import darkFinances from '../../../image/band-space/dark-finances.webp'
import darkMembres from '../../../image/band-space/dark-membres.webp'
import darkNotes from '../../../image/band-space/dark-notes.webp'
import darkSetlists from '../../../image/band-space/dark-setlists.webp'
import darkTaches from '../../../image/band-space/dark-taches.webp'
import lightAgenda from '../../../image/band-space/light-agenda.webp'
import lightFiles from '../../../image/band-space/light-files.webp'
import lightFinances from '../../../image/band-space/light-finances.webp'
import lightMembres from '../../../image/band-space/light-membres.webp'
import lightNotes from '../../../image/band-space/light-notes.webp'
import lightSetlists from '../../../image/band-space/light-setlists.webp'
import lightTaches from '../../../image/band-space/light-taches.webp'
import { useDarkMode } from '../../composables/useDarkMode.js'
import { BAND_SPACE_MODULES } from '../../constants/bandSpace.js'
import { adjacentTabKey } from '../../utils/tabNavigation.js'

const router = useRouter()
const { isDarkMode } = useDarkMode()

// Keyed by module, so a module added to BAND_SPACE_MODULES without a capture fails visibly here
// rather than rendering a broken image.
const lightShots = {
  agenda: lightAgenda,
  taches: lightTaches,
  notes: lightNotes,
  setlists: lightSetlists,
  files: lightFiles,
  finances: lightFinances
}
const darkShots = {
  agenda: darkAgenda,
  taches: darkTaches,
  notes: darkNotes,
  setlists: darkSetlists,
  files: darkFiles,
  finances: darkFinances
}

const activeModuleKey = ref(BAND_SPACE_MODULES[0].key)

function selectModule(key) {
  activeModuleKey.value = key
  document.getElementById(`module-tab-${key}`)?.focus()
}

/** Arrows move between tabs, Home and End jump to the ends, as a tablist is expected to. */
function moveByArrow(step) {
  selectModule(adjacentTabKey(BAND_SPACE_MODULES, activeModuleKey.value, step))
}

function moveToEdge(edge) {
  selectModule(edge === 'first' ? BAND_SPACE_MODULES[0].key : BAND_SPACE_MODULES.at(-1).key)
}

const replacements = [
  {
    before: 'La conversation de groupe où la date du concert est remontée à la main :',
    after: 'un agenda que tout le monde voit.'
  },
  {
    before: "Le tableur des comptes que personne n'ose modifier :",
    after: 'des dépenses et une part par membre, calculées.'
  },
  {
    before: 'Le dossier partagé où traînent quatre versions de la même setlist :',
    after: 'une setlist, à jour, imprimable.'
  }
]

function goToRegister() {
  router.push({ name: 'app_register' })
}

function goToLogin() {
  router.push({ name: 'app_login' })
}
</script>
