<template>
  <div
    ref="rootEl"
    class="fixed inset-0 bg-surface-950 text-surface-0 flex flex-col select-none touch-pan-y"
  >
    <div v-if="isLoading" class="flex-1 flex items-center justify-center text-surface-400">
      <i class="pi pi-spin pi-spinner text-3xl mr-3"></i>
      Chargement…
    </div>

    <div v-else-if="loadError" class="flex-1 flex flex-col items-center justify-center gap-4 p-6 text-center">
      <i class="pi pi-exclamation-triangle text-4xl text-amber-500"></i>
      <p class="text-xl">{{ loadError }}</p>
      <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" @click="exitLive" />
    </div>

    <div v-else-if="items.length === 0" class="flex-1 flex flex-col items-center justify-center gap-4 p-6 text-center">
      <i class="pi pi-headphones text-4xl text-surface-400"></i>
      <p class="text-xl">Cette setlist est vide.</p>
      <Button label="Retour à l'éditeur" icon="pi pi-arrow-left" severity="secondary" @click="exitLive" />
    </div>

    <template v-else>
      <!-- Sortie button isolated in a top-right corner so it's hard to mis-tap
           when reaching for Suivant. -->
      <div class="absolute top-3 right-3 flex items-center gap-2 z-10">
        <Button
          icon="pi pi-list"
          severity="secondary"
          rounded
          aria-label="Aperçu"
          v-tooltip.bottom="'Aperçu de la setlist'"
          @click="overviewOpen = true"
        />
        <Button
          icon="pi pi-times"
          severity="danger"
          rounded
          aria-label="Quitter le mode Live"
          v-tooltip.bottom="'Quitter le mode Live'"
          @click="exitLive"
        />
      </div>

      <main class="flex-1 flex flex-col items-center justify-center px-6 py-8 text-center">
        <div class="text-xs uppercase tracking-widest text-surface-400 mb-3">
          {{ setlistName }}
        </div>

        <i :class="currentTypeIcon" class="text-3xl mb-4"></i>

        <h1 class="text-5xl lg:text-7xl font-bold leading-tight max-w-5xl break-words">
          {{ currentTitle }}
        </h1>

        <div v-if="currentMeta.length > 0" class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-2xl text-surface-300">
          <span v-for="meta in currentMeta" :key="meta.key">{{ meta.label }}</span>
        </div>

        <div v-if="current?.transition" class="mt-4 text-lg text-rose-300">
          → {{ current.transition }}
        </div>
        <div v-if="current?.note" class="mt-2 text-lg text-surface-400 italic max-w-3xl">
          {{ current.note }}
        </div>
      </main>

      <footer class="bg-surface-900 border-t border-surface-800 px-4 py-4 flex items-center gap-3">
        <Button
          icon="pi pi-chevron-left"
          severity="secondary"
          size="large"
          class="!py-6 !px-8"
          :disabled="currentIndex === 0"
          aria-label="Précédent"
          @click="goPrev"
        />

        <div class="flex-1 text-center">
          <div class="text-xs uppercase tracking-widest text-surface-400">Position</div>
          <div class="text-2xl tabular-nums font-semibold">
            {{ currentIndex + 1 }} / {{ items.length }}
          </div>
        </div>

        <Button
          icon="pi pi-chevron-right"
          severity="secondary"
          size="large"
          class="!py-6 !px-8"
          :disabled="currentIndex === items.length - 1"
          aria-label="Suivant"
          @click="goNext"
        />
      </footer>

      <Drawer
        v-model:visible="overviewOpen"
        position="right"
        :style="{ width: '22rem' }"
      >
        <template #header>
          <span class="font-semibold">{{ setlistName }}</span>
        </template>
        <ol class="flex flex-col gap-1">
          <li v-for="(item, idx) in items" :key="item.id">
            <button
              type="button"
              class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
              :class="
                idx === currentIndex
                  ? 'bg-primary text-primary-contrast font-semibold'
                  : 'hover:bg-surface-100 dark:hover:bg-surface-800'
              "
              @click="jumpTo(idx)"
            >
              <span class="tabular-nums text-sm w-6 text-right">{{ idx + 1 }}</span>
              <i :class="iconFor(item)" class="text-base shrink-0"></i>
              <span class="flex-1 truncate">{{ titleFor(item) }}</span>
              <span class="text-xs text-surface-500 tabular-nums">{{ durationFor(item) }}</span>
            </button>
          </li>
        </ol>
      </Drawer>
    </template>
  </div>
</template>

<script setup>
import { useSwipe } from '@vueuse/core'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBandSetlistsStore } from '../../store/bandSpace/bandSpaceSetlists.js'

const route = useRoute()
const router = useRouter()
const setlistsStore = useBandSetlistsStore()

const rootEl = ref(null)
const currentIndex = ref(0)
const overviewOpen = ref(false)

const bandSpaceId = computed(() => route.params.bandSpaceId)
const setlistId = computed(() => route.params.setlistId)

const isLoading = computed(() => setlistsStore.isLoadingActive && !setlistsStore.activeSetlist)
const loadError = computed(() => setlistsStore.loadError)

const setlist = computed(() => {
  const s = setlistsStore.activeSetlist
  if (!s || s.id !== setlistId.value) return null
  return s
})
const setlistName = computed(() => setlist.value?.name ?? '')
const items = computed(() =>
  setlist.value?.items ? [...setlist.value.items].sort((a, b) => a.position - b.position) : []
)
const current = computed(() => items.value[currentIndex.value] ?? null)

function iconFor(item) {
  switch (item?.type) {
    case 'song':
      return 'pi pi-headphones text-emerald-500'
    case 'interlude':
      return 'pi pi-volume-up text-sky-500'
    case 'break':
      return 'pi pi-pause text-amber-500'
    case 'talk':
      return 'pi pi-microphone text-purple-500'
    default:
      return 'pi pi-circle'
  }
}

function titleFor(item) {
  if (!item) return ''
  if (item.type === 'song' && item.song) return item.song.title
  return item.label || '—'
}

function durationFor(item) {
  const duration = item?.duration_override ?? item?.song?.reference_duration ?? null
  if (!duration) return ''
  const m = Math.floor(duration / 60)
  const s = duration % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

const currentTypeIcon = computed(() => iconFor(current.value))
const currentTitle = computed(() => titleFor(current.value))

const currentMeta = computed(() => {
  const item = current.value
  if (!item) return []
  const meta = []
  if (item.type === 'song' && item.song?.tonality) {
    meta.push({ key: 'tonality', label: item.song.tonality })
  }
  if (item.type === 'song' && item.song?.tempo) {
    meta.push({ key: 'tempo', label: `${item.song.tempo} BPM` })
  }
  const duration = item.duration_override ?? item.song?.reference_duration ?? null
  if (duration) {
    const m = Math.floor(duration / 60)
    const s = duration % 60
    meta.push({ key: 'duration', label: `${m}′${String(s).padStart(2, '0')}″` })
  }
  return meta
})

function goPrev() {
  if (currentIndex.value > 0) currentIndex.value--
}

function goNext() {
  if (currentIndex.value < items.value.length - 1) currentIndex.value++
}

function jumpTo(idx) {
  if (idx >= 0 && idx < items.value.length) {
    currentIndex.value = idx
    overviewOpen.value = false
  }
}

function exitLive() {
  // Prefer browser back when we came from the editor (or anywhere with
  // navigable history); fall back to a direct editor push when the user
  // deep-linked into /live.
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push({
      name: 'app_band_setlist',
      params: { id: bandSpaceId.value },
      query: { setlist: setlistId.value }
    })
  }
}

// Swipe navigation
const { direction, isSwiping } = useSwipe(rootEl, {
  threshold: 60,
  onSwipeEnd() {
    if (!isSwiping.value) {
      if (direction.value === 'left') goNext()
      else if (direction.value === 'right') goPrev()
    }
  }
})

// Window-level keyboard handler so it works regardless of focus.
// Ignore keys when a BUTTON has focus — Space would otherwise both activate
// the focused button AND advance, jumping two items.
function handleKeydown(event) {
  if (event.target?.tagName === 'BUTTON' || event.target?.tagName === 'INPUT') return
  if (overviewOpen.value) return
  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    goPrev()
  } else if (event.key === 'ArrowRight' || event.key === ' ') {
    event.preventDefault()
    goNext()
  }
}

// Wake lock (graceful no-op on unsupported browsers)
let wakeLockSentinel = null
async function requestWakeLock() {
  if (!('wakeLock' in navigator)) return
  try {
    wakeLockSentinel = await navigator.wakeLock.request('screen')
    // System can release the lock unprompted (low battery, policy, etc.) —
    // keep our local state honest so visibilitychange can re-request.
    wakeLockSentinel.addEventListener('release', () => {
      wakeLockSentinel = null
    })
  } catch {
    // Permission denied or page hidden — silently ignore.
  }
}
function releaseWakeLock() {
  if (wakeLockSentinel) {
    wakeLockSentinel.release().catch(() => {})
    wakeLockSentinel = null
  }
}

// Re-request the wake lock when the page becomes visible again (browsers
// release it on tab switch / lock screen).
function handleVisibilityChange() {
  if (document.visibilityState === 'visible' && !wakeLockSentinel) {
    requestWakeLock()
  }
}

// Reset currentIndex if the setlist changes (e.g. items reload).
watch(items, (next, prev) => {
  if (prev && next.length !== prev.length) {
    currentIndex.value = Math.min(currentIndex.value, Math.max(0, next.length - 1))
  }
})

onMounted(() => {
  if (setlistId.value) {
    setlistsStore.fetchActive(bandSpaceId.value, setlistId.value)
  }
  requestWakeLock()
  window.addEventListener('keydown', handleKeydown)
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  releaseWakeLock()
})
</script>
