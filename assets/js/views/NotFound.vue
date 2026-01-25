<template>
  <div class="min-h-[70vh] flex flex-col items-center justify-center px-4 py-12">
    <!-- Main Content -->
    <div class="text-center mb-8">
      <h1 class="text-6xl md:text-8xl font-bold text-primary mb-4">404</h1>
      <p class="text-xl md:text-2xl text-surface-600 dark:text-surface-400 mb-2">
        {{ pageTitle }}
      </p>
      <p v-if="playMessage" class="text-lg text-primary/80 dark:text-primary/70 font-medium">
        {{ playMessage }}
      </p>
      <p v-if="easterEggMessage" class="text-lg text-fuchsia-500 dark:text-fuchsia-400 font-medium animate-pulse">
        {{ easterEggMessage }}
      </p>
    </div>

    <!-- Piano Keyboard -->
    <div class="mb-8 select-none">
      <p class="text-sm text-surface-500 dark:text-surface-400 text-center mb-3">
        <i class="pi pi-music mr-2" />
        Jouez quelques notes en attendant
        <span class="hidden sm:inline">(clavier : A Z E R T Y U I)</span>
      </p>

      <div
        ref="pianoRef"
        class="relative h-40 sm:h-48 touch-none"
        role="application"
        aria-label="Piano interactif"
      >
        <!-- White keys -->
        <div class="flex h-full">
          <button
            v-for="(key, index) in whiteKeys"
            :key="key.note"
            :class="[
              'relative h-full w-10 sm:w-12 md:w-14 border border-surface-300 dark:border-surface-600 rounded-b-lg transition-all duration-75 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset',
              activeKeys.has(key.note)
                ? 'bg-primary/20 dark:bg-primary/30 translate-y-0.5'
                : 'bg-white dark:bg-surface-100 hover:bg-surface-50 dark:hover:bg-surface-200',
              index > 0 ? '-ml-px' : ''
            ]"
            :aria-label="`Note ${key.note}`"
            :aria-pressed="activeKeys.has(key.note)"
            @mousedown="playNote(key.note, key.frequency)"
            @mouseup="stopNote(key.note)"
            @mouseleave="stopNote(key.note)"
            @touchstart.prevent="playNote(key.note, key.frequency)"
            @touchend.prevent="stopNote(key.note)"
          >
            <span class="absolute bottom-2 left-1/2 -translate-x-1/2 text-xs text-surface-400 dark:text-surface-500 pointer-events-none">
              {{ key.keyLabel }}
            </span>
          </button>
        </div>

        <!-- Black keys -->
        <div class="absolute top-0 left-0 h-[60%] flex pointer-events-none">
          <template v-for="(key, index) in blackKeyPositions" :key="index">
            <div
              :class="['h-full', key ? 'w-10 sm:w-12 md:w-14' : 'w-10 sm:w-12 md:w-14']"
            >
              <button
                v-if="key"
                :class="[
                  'pointer-events-auto absolute h-full w-6 sm:w-7 md:w-8 rounded-b-md transition-all duration-75 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset z-10',
                  activeKeys.has(key.note)
                    ? 'bg-surface-600 dark:bg-surface-500 translate-y-0.5'
                    : 'bg-surface-900 dark:bg-surface-950 hover:bg-surface-800 dark:hover:bg-surface-900'
                ]"
                :style="{ left: `${getBlackKeyOffset(index)}px` }"
                :aria-label="`Note ${key.note}`"
                :aria-pressed="activeKeys.has(key.note)"
                @mousedown="playNote(key.note, key.frequency)"
                @mouseup="stopNote(key.note)"
                @mouseleave="stopNote(key.note)"
                @touchstart.prevent="playNote(key.note, key.frequency)"
                @touchend.prevent="stopNote(key.note)"
              >
                <span class="absolute bottom-2 left-1/2 -translate-x-1/2 text-[10px] text-surface-400 pointer-events-none">
                  {{ key.keyLabel }}
                </span>
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Navigation Section -->
    <div class="w-full max-w-md space-y-4">
      <!-- Explanatory text -->
      <p class="text-center text-surface-600 dark:text-surface-400">
        La page que vous cherchez n'existe pas ou a été déplacée.<br />
        En attendant, pourquoi ne pas :
      </p>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-3">
        <router-link :to="{ name: 'app_search_musician' }" class="flex-1" @click="trackClick('musician-search')">
          <Button
            label="Trouver un musicien"
            icon="pi pi-users"
            severity="secondary"
            outlined
            class="w-full"
          />
        </router-link>
        <Button
          label="Poster une annonce"
          icon="pi pi-megaphone"
          severity="info"
          class="flex-1"
          @click="handleOpenAnnounceModal"
        />
      </div>

      <!-- Home Link -->
      <div class="text-center pt-2">
        <router-link
          :to="{ name: 'app_home' }"
          class="text-primary hover:underline inline-flex items-center gap-2"
          @click="trackClick('home')"
        >
          <i class="pi pi-home" />
          Retour à l'accueil
        </router-link>
      </div>
    </div>

    <!-- Modals -->
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
    <AddAnnounceModal v-model:visible="showAnnounceModal" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AuthRequiredModal from '../components/Auth/AuthRequiredModal.vue'
import AddAnnounceModal from './User/Announce/AddAnnounceModal.vue'
import { useUserSecurityStore } from '../store/user/security.js'

useTitle('Page introuvable - MusicAll')

const userSecurityStore = useUserSecurityStore()

// State
const showAuthModal = ref(false)
const showAnnounceModal = ref(false)
const authModalMessage = ref('')
const notesPlayed = ref(0)
const playStartTime = ref(null)
const activeKeys = ref(new Set())
const recentNotes = ref([])
const easterEggMessage = ref('')
const playMessage = ref('')
const pianoRef = ref(null)

// Audio context
let audioContext = null
const oscillators = new Map()

// Random page title (selected once on load)
const pageTitles = [
  'Cette page a rejoint un groupe de prog, on la revoit dans 20 minutes',
  'Erreur 404 : partition introuvable',
  'Oups, fausse note !',
  'Cette page a joué en do quand il fallait jouer en ré',
  'Ce solo était pas prévu',
  'Quelqu\'un a débranché l\'ampli',
  'Le bassiste s\'est encore perdu',
]
const pageTitle = pageTitles[Math.floor(Math.random() * pageTitles.length)]

// Messages for playing
const playMessages = {
  playing: [
    'Pas mal du tout !',
    'Joli !',
    'Vous avez du talent !',
    'La musique adoucit les 404',
  ],
  longPlay: [
    'Prêt·e à trouver un groupe ?',
    'Un vrai concert ! Mais la page n\'est toujours pas là...',
    'Vous devriez poster une annonce !',
  ],
}

function updatePlayMessage() {
  if (playStartTime.value) {
    const playDuration = (Date.now() - playStartTime.value) / 1000
    if (playDuration > 10 && notesPlayed.value >= 10) {
      playMessage.value = playMessages.longPlay[Math.floor(Math.random() * playMessages.longPlay.length)]
      return
    }
  }
  if (notesPlayed.value >= 5) {
    playMessage.value = playMessages.playing[Math.floor(Math.random() * playMessages.playing.length)]
  }
}

// Piano configuration (one octave: C4 to C5)
const whiteKeys = [
  { note: 'C4', frequency: 261.63, keyLabel: 'A' },
  { note: 'D4', frequency: 293.66, keyLabel: 'Z' },
  { note: 'E4', frequency: 329.63, keyLabel: 'E' },
  { note: 'F4', frequency: 349.23, keyLabel: 'R' },
  { note: 'G4', frequency: 392.00, keyLabel: 'T' },
  { note: 'A4', frequency: 440.00, keyLabel: 'Y' },
  { note: 'B4', frequency: 493.88, keyLabel: 'U' },
  { note: 'C5', frequency: 523.25, keyLabel: 'I' },
]

const blackKeys = [
  { note: 'C#4', frequency: 277.18, keyLabel: '2' },
  { note: 'D#4', frequency: 311.13, keyLabel: '3' },
  null, // No black key between E and F
  { note: 'F#4', frequency: 369.99, keyLabel: '5' },
  { note: 'G#4', frequency: 415.30, keyLabel: '6' },
  { note: 'A#4', frequency: 466.16, keyLabel: '7' },
  null, // No black key between B and C
]

const blackKeyPositions = computed(() => blackKeys)

// AZERTY keyboard mapping
const keyMap = {
  'a': whiteKeys[0], // C4
  'z': whiteKeys[1], // D4
  'e': whiteKeys[2], // E4
  'r': whiteKeys[3], // F4
  't': whiteKeys[4], // G4
  'y': whiteKeys[5], // A4
  'u': whiteKeys[6], // B4
  'i': whiteKeys[7], // C5
  '2': blackKeys[0], // C#4
  '3': blackKeys[1], // D#4
  '5': blackKeys[3], // F#4
  '6': blackKeys[4], // G#4
  '7': blackKeys[5], // A#4
}

// Smoke on the Water detection (G4, Bb4, C5, G4, Bb4, Db5, C5)
// Simplified pattern: G4, A#4, C5
const smokeOnTheWaterPattern = ['G4', 'A#4', 'C5']

function getBlackKeyOffset(index) {
  // Calculate offset for black keys to position them between white keys
  const baseWidth = pianoRef.value ? pianoRef.value.querySelector('button')?.offsetWidth || 48 : 48
  const blackWidth = baseWidth * 0.6
  const offsets = [
    baseWidth - blackWidth / 2,           // C#
    baseWidth * 2 - blackWidth / 2,       // D#
    0,                                     // (no key)
    baseWidth * 4 - blackWidth / 2,       // F#
    baseWidth * 5 - blackWidth / 2,       // G#
    baseWidth * 6 - blackWidth / 2,       // A#
    0,                                     // (no key)
  ]
  return offsets[index] || 0
}

function initAudio() {
  if (!audioContext) {
    audioContext = new (window.AudioContext || window.webkitAudioContext)()
  }
  if (audioContext.state === 'suspended') {
    audioContext.resume()
  }
}

function playNote(note, frequency) {
  initAudio()

  if (oscillators.has(note)) return

  const oscillator = audioContext.createOscillator()
  const gainNode = audioContext.createGain()

  oscillator.type = 'triangle'
  oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime)

  gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
  gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1.5)

  oscillator.connect(gainNode)
  gainNode.connect(audioContext.destination)

  oscillator.start()
  oscillators.set(note, { oscillator, gainNode })

  activeKeys.value.add(note)
  activeKeys.value = new Set(activeKeys.value)

  // Track playing
  if (!playStartTime.value) {
    playStartTime.value = Date.now()
  }
  notesPlayed.value++

  // Track for easter egg
  recentNotes.value.push(note)
  if (recentNotes.value.length > 10) {
    recentNotes.value.shift()
  }
  checkEasterEgg()
  updatePlayMessage()
}

function stopNote(note) {
  const osc = oscillators.get(note)
  if (osc) {
    osc.gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.1)
    setTimeout(() => {
      osc.oscillator.stop()
      oscillators.delete(note)
    }, 100)
  }

  activeKeys.value.delete(note)
  activeKeys.value = new Set(activeKeys.value)
}

function checkEasterEgg() {
  // Check for Smoke on the Water pattern
  const lastThree = recentNotes.value.slice(-3)
  if (lastThree.length === 3 &&
      lastThree[0] === smokeOnTheWaterPattern[0] &&
      lastThree[1] === smokeOnTheWaterPattern[1] &&
      lastThree[2] === smokeOnTheWaterPattern[2]) {
    easterEggMessage.value = '🎸 Smoke on the Water ! Deep Purple serait fier !'
    trackUmamiEvent('404-easter-egg', { song: 'smoke-on-the-water' })
    setTimeout(() => {
      easterEggMessage.value = ''
    }, 5000)
  }
}

function handleKeyDown(event) {
  if (event.repeat) return
  if (document.activeElement?.tagName === 'INPUT') return

  const key = event.key.toLowerCase()
  const noteData = keyMap[key]
  if (noteData) {
    event.preventDefault()
    playNote(noteData.note, noteData.frequency)
  }
}

function handleKeyUp(event) {
  const key = event.key.toLowerCase()
  const noteData = keyMap[key]
  if (noteData) {
    stopNote(noteData.note)
  }
}

function handleOpenAnnounceModal() {
  trackClick('announce-modal')
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Si vous souhaitez poster une annonce, vous devez vous connecter.'
    showAuthModal.value = true
    return
  }
  showAnnounceModal.value = true
}

function trackClick(action) {
  trackUmamiEvent('404-click', { action })
}

onMounted(() => {
  trackUmamiEvent('404-page-view')
  window.addEventListener('keydown', handleKeyDown)
  window.addEventListener('keyup', handleKeyUp)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('keyup', handleKeyUp)

  // Clean up audio
  oscillators.forEach((osc) => {
    try {
      osc.oscillator.stop()
    } catch {
      // Oscillator may already be stopped
    }
  })
  oscillators.clear()

  // Track notes played if any
  if (notesPlayed.value > 0) {
    trackUmamiEvent('404-piano-played', { notes_count: notesPlayed.value })
  }
})
</script>
