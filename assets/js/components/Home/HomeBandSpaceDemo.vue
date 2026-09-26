<template>
  <div
    class="grid sm:grid-cols-[11rem_minmax(0,1fr)] overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-950 shadow-2xl sm:min-h-[26rem]"
  >
    <div class="flex flex-col gap-1 border-b sm:border-b-0 sm:border-r border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 p-3">
      <div class="hidden sm:flex items-center gap-2.5 px-2 pb-4 pt-1">
        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-400 to-fuchsia-400" aria-hidden="true" />
        <span class="text-sm font-bold text-surface-900 dark:text-surface-0">Votre groupe</span>
      </div>
      <div
        class="flex sm:flex-col flex-wrap gap-1"
        role="tablist"
        aria-label="Modules du Band Space"
      >
        <button
          v-for="module in BAND_SPACE_MODULES"
          :id="`home-demo-tab-${module.key}`"
          :key="module.key"
          type="button"
          role="tab"
          :aria-selected="module.key === activeKey"
          :aria-controls="`home-demo-panel-${module.key}`"
          :tabindex="module.key === activeKey ? 0 : -1"
          :class="[
            'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-left cursor-pointer transition-colors',
            module.key === activeKey
              ? 'bg-primary-100 text-primary-800 font-semibold dark:bg-surface-800 dark:text-surface-0'
              : 'text-surface-700 hover:bg-surface-200 dark:text-surface-300 dark:hover:bg-surface-800'
          ]"
          @click="activeKey = module.key"
          @keydown.up.prevent="moveBy(-1)"
          @keydown.left.prevent="moveBy(-1)"
          @keydown.down.prevent="moveBy(1)"
          @keydown.right.prevent="moveBy(1)"
          @keydown.home.prevent="select(BAND_SPACE_MODULES[0].key)"
          @keydown.end.prevent="select(BAND_SPACE_MODULES.at(-1).key)"
        >
          <i :class="module.icon" aria-hidden="true" />
          {{ module.label }}
        </button>
      </div>
    </div>

    <!-- Every panel stays mounted so each tab's aria-controls points at a real element. -->
    <div
      v-for="module in BAND_SPACE_MODULES"
      v-show="module.key === activeKey"
      :id="`home-demo-panel-${module.key}`"
      :key="module.key"
      role="tabpanel"
      :aria-labelledby="`home-demo-tab-${module.key}`"
      class="flex flex-col gap-4 p-5"
    >
      <div class="flex items-baseline justify-between gap-3">
        <h3 class="m-0 text-base font-bold text-surface-900 dark:text-surface-0">{{ DEMO[module.key].title }}</h3>
        <span class="text-xs text-surface-600 dark:text-surface-300">Aperçu illustratif</span>
      </div>

      <template v-if="module.key === 'agenda'">
        <div v-for="event in DEMO.agenda.events" :key="event.name" :class="TILE" class="flex items-center gap-4">
          <div class="flex flex-col items-center w-12 shrink-0">
            <span class="text-xs font-bold" :class="event.accent">{{ event.day }}</span>
            <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ event.date }}</span>
          </div>
          <div class="flex flex-col min-w-0">
            <span class="font-semibold text-surface-900 dark:text-surface-0">{{ event.name }}</span>
            <span class="text-sm text-surface-600 dark:text-surface-300">{{ event.detail }}</span>
          </div>
        </div>
      </template>

      <ul v-else-if="module.key === 'taches'" class="m-0 p-0 list-none flex flex-col gap-2">
        <li v-for="task in DEMO.taches.tasks" :key="task.label" :class="TILE" class="flex items-center gap-3">
          <span
            class="w-4 h-4 shrink-0 rounded border-2"
            :class="task.done ? 'bg-teal-600 border-teal-600 dark:bg-teal-400 dark:border-teal-400' : 'border-surface-400 dark:border-surface-500'"
            aria-hidden="true"
          />
          <span class="flex-1 text-sm" :class="task.done ? 'line-through text-surface-600 dark:text-surface-400' : 'text-surface-900 dark:text-surface-0'">
            {{ task.label }}<span v-if="task.done" class="sr-only"> (fait)</span>
          </span>
          <span class="text-xs text-surface-600 dark:text-surface-300">{{ task.who }}</span>
        </li>
      </ul>

      <div v-else-if="module.key === 'notes'" :class="TILE" class="flex flex-col gap-2">
        <span class="font-semibold text-surface-900 dark:text-surface-0">{{ DEMO.notes.name }}</span>
        <p v-for="line in DEMO.notes.lines" :key="line" class="m-0 text-sm leading-relaxed text-surface-700 dark:text-surface-200">
          {{ line }}
        </p>
        <span class="text-xs text-surface-600 dark:text-surface-300">{{ DEMO.notes.edited }}</span>
      </div>

      <div v-else-if="module.key === 'setlists'" :class="TILE" class="flex flex-col gap-2">
        <div class="flex justify-between gap-3">
          <span class="font-semibold text-surface-900 dark:text-surface-0">{{ DEMO.setlists.name }}</span>
          <span class="text-sm text-surface-600 dark:text-surface-300">{{ DEMO.setlists.total }}</span>
        </div>
        <ol class="m-0 p-0 list-none flex flex-col gap-1.5">
          <li v-for="(song, index) in DEMO.setlists.songs" :key="song.title" class="flex justify-between gap-3 text-sm">
            <span class="text-surface-900 dark:text-surface-0">{{ index + 1 }}. {{ song.title }}</span>
            <span class="text-surface-600 dark:text-surface-300">{{ song.duration }}</span>
          </li>
        </ol>
      </div>

      <ul v-else-if="module.key === 'files'" class="m-0 p-0 list-none flex flex-col gap-2">
        <li v-for="file in DEMO.files.files" :key="file.name" :class="TILE" class="flex items-center gap-3">
          <i :class="[file.icon, 'text-fuchsia-700 dark:text-fuchsia-300']" aria-hidden="true" />
          <span class="flex-1 min-w-0 truncate text-sm text-surface-900 dark:text-surface-0">{{ file.name }}</span>
          <span class="text-xs text-surface-600 dark:text-surface-300">{{ file.size }}</span>
        </li>
      </ul>

      <template v-else-if="module.key === 'finances'">
        <div :class="TILE" class="flex items-center justify-between gap-3">
          <span class="text-sm text-surface-700 dark:text-surface-200">Caisse du groupe</span>
          <span class="text-xl font-bold text-surface-900 dark:text-surface-0">{{ DEMO.finances.balance }}</span>
        </div>
        <ul class="m-0 p-0 list-none flex flex-col gap-2">
          <li v-for="entry in DEMO.finances.entries" :key="entry.label" :class="TILE" class="flex items-center justify-between gap-3 text-sm">
            <span class="text-surface-900 dark:text-surface-0">{{ entry.label }}</span>
            <span
              class="font-semibold"
              :class="entry.income ? 'text-green-700 dark:text-green-400' : 'text-surface-700 dark:text-surface-200'"
            >
              {{ entry.amount }}
            </span>
          </li>
        </ul>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { BAND_SPACE_MODULES } from '../../constants/bandSpace.js'
import { adjacentTabKey } from '../../utils/tabNavigation.js'

/**
 * A pretend Band Space for the homepage pitch (#1074): clickable, and deliberately simpler than the
 * real modules, since it sells the idea rather than documents the screens.
 */
const TILE =
  'rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 px-4 py-3'

// Keyed by module, so a module added to BAND_SPACE_MODULES without content here fails visibly.
const DEMO = {
  agenda: {
    title: 'Cette semaine',
    events: [
      {
        day: 'JEU',
        date: '01',
        name: 'Répétition',
        detail: '20:00 à 23:00 · Local de répète',
        accent: 'text-primary'
      },
      {
        day: 'SAM',
        date: '03',
        name: 'Concert',
        detail: '21:30 · Le Garage · setlist « Tournée »',
        accent: 'text-fuchsia-700 dark:text-fuchsia-300'
      },
      {
        day: 'MAR',
        date: '06',
        name: 'Enregistrement démo',
        detail: '14:00 · Studio',
        accent: 'text-primary'
      }
    ]
  },
  taches: {
    title: 'Tâches',
    tasks: [
      { label: 'Envoyer le tech rider', who: 'Léa', done: false },
      { label: 'Réserver le local', who: 'Tom', done: false },
      { label: 'Relancer la salle', who: 'Sam', done: false },
      { label: 'Payer la salle', who: 'Léa', done: true }
    ]
  },
  notes: {
    title: 'Notes',
    name: 'Compte rendu de la répét',
    lines: [
      'Le pont de « Nuit blanche » passe à 8 mesures.',
      'On garde la reprise pour le rappel.',
      'Tom apporte la deuxième caisse claire samedi.'
    ],
    edited: 'Modifiée par Léa hier'
  },
  setlists: {
    title: 'Setlists',
    name: 'Tournée',
    total: '5 morceaux · 21 min',
    songs: [
      { title: 'Nuit blanche', duration: '4:12' },
      { title: 'Retour de flamme', duration: '3:48' },
      { title: 'Béton', duration: '5:05' },
      { title: 'Le dernier train', duration: '4:20' },
      { title: 'Rappel', duration: '3:35' }
    ]
  },
  files: {
    title: 'Fichiers',
    files: [
      { name: 'demo-nuit-blanche.mp3', size: '4,2 Mo', icon: 'pi pi-volume-up' },
      { name: 'partition-basse-beton.pdf', size: '320 Ko', icon: 'pi pi-file-pdf' },
      { name: 'tech-rider.pdf', size: '180 Ko', icon: 'pi pi-file-pdf' },
      { name: 'photo-concert.jpg', size: '2,1 Mo', icon: 'pi pi-image' }
    ]
  },
  finances: {
    title: 'Finances',
    balance: '420,00 €',
    entries: [
      { label: 'Cachet du concert', amount: '+ 300,00 €', income: true },
      { label: 'Location du local', amount: '- 80,00 €', income: false },
      { label: 'Cordes et baguettes', amount: '- 25,00 €', income: false }
    ]
  }
}

const activeKey = ref(BAND_SPACE_MODULES[0].key)

function select(key) {
  activeKey.value = key
  document.getElementById(`home-demo-tab-${key}`)?.focus()
}

function moveBy(step) {
  select(adjacentTabKey(BAND_SPACE_MODULES, activeKey.value, step))
}
</script>
