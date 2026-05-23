<template>
  <div>
    <div v-if="songsStore.loadError || setlistsStore.loadError" class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4">
      <Message severity="error" :closable="false">
        {{ songsStore.loadError || setlistsStore.loadError }}
      </Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="loadAll" />
    </div>

    <div v-else class="flex flex-col lg:flex-row gap-6">
      <aside class="hidden lg:block w-64 shrink-0 bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
        <SidebarContent
          :songs-count="songsStore.songs.length"
          :setlists="setlistsStore.setlists"
          :is-loading-setlists="setlistsStore.isLoading"
          :active-view="activeView"
          :active-setlist-id="activeSetlistId"
          @select-repertoire="selectRepertoire"
          @select-setlist="selectSetlist"
        />
      </aside>

      <div class="lg:hidden bg-surface-0 dark:bg-surface-900 rounded-2xl p-3 border border-surface-200 dark:border-surface-700">
        <button
          type="button"
          class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-surface-50 dark:hover:bg-surface-800"
          @click="mobileNavOpen = true"
        >
          <i class="pi pi-bars text-surface-500"></i>
          <span class="flex-1 text-left truncate font-medium">{{ currentSelectionLabel }}</span>
          <i class="pi pi-chevron-down text-surface-400"></i>
        </button>
      </div>

      <Drawer v-model:visible="mobileNavOpen" position="left" :style="{ width: '20rem' }">
        <template #header>
          <span class="font-semibold">Navigation Setlists</span>
        </template>
        <SidebarContent
          :songs-count="songsStore.songs.length"
          :setlists="setlistsStore.setlists"
          :is-loading-setlists="setlistsStore.isLoading"
          :active-view="activeView"
          :active-setlist-id="activeSetlistId"
          @select-repertoire="selectRepertoire"
          @select-setlist="selectSetlist"
        />
      </Drawer>

      <section class="flex-1 min-w-0">
        <RepertoireView
          v-if="activeView === 'repertoire'"
          :band-space-id="bandSpaceId"
        />
        <div
          v-else-if="activeView === 'setlist'"
          class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 border border-surface-200 dark:border-surface-700 text-center text-surface-500"
        >
          <i class="pi pi-clock text-3xl mb-3 block"></i>
          L'éditeur de setlist arrive bientôt.
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Message from 'primevue/message'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RepertoireView from '../../components/BandSpace/Setlist/RepertoireView.vue'
import SidebarContent from '../../components/BandSpace/Setlist/SidebarContent.vue'
import { useBandSetlistsStore } from '../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../store/bandSpace/bandSpaceSongs.js'

const route = useRoute()
const router = useRouter()
const songsStore = useBandSongsStore()
const setlistsStore = useBandSetlistsStore()

// Wipe any previous space's data synchronously before first render.
songsStore.clear()
setlistsStore.clear()

const bandSpaceId = computed(() => route.params.id)

const activeView = ref('repertoire') // 'repertoire' | 'setlist'
const activeSetlistId = ref(null)
const mobileNavOpen = ref(false)

const activeSetlist = computed(() =>
  activeSetlistId.value
    ? (setlistsStore.setlists.find((s) => s.id === activeSetlistId.value) ?? null)
    : null
)
const currentSelectionLabel = computed(() =>
  activeView.value === 'setlist' && activeSetlist.value ? activeSetlist.value.name : 'Répertoire'
)

function selectRepertoire() {
  activeView.value = 'repertoire'
  activeSetlistId.value = null
  router.replace({ query: {} })
  mobileNavOpen.value = false
}

function selectSetlist(id) {
  activeView.value = 'setlist'
  activeSetlistId.value = id
  router.replace({ query: { setlist: id } })
  mobileNavOpen.value = false
}

function syncFromQuery() {
  const setlistParam = route.query.setlist
  if (typeof setlistParam === 'string' && setlistParam) {
    activeView.value = 'setlist'
    activeSetlistId.value = setlistParam
    return
  }
  activeView.value = 'repertoire'
  activeSetlistId.value = null
}

function loadAll() {
  if (!bandSpaceId.value) return
  songsStore.fetchSongs(bandSpaceId.value)
  setlistsStore.fetchSetlists(bandSpaceId.value)
}

onMounted(() => {
  syncFromQuery()
  loadAll()
})

onUnmounted(() => {
  songsStore.clear()
  setlistsStore.clear()
})

watch(
  () => route.query,
  () => syncFromQuery()
)
</script>
