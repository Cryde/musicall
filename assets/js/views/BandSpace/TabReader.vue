<template>
  <div class="flex-1 min-h-0 flex flex-col gap-3 max-md:gap-0">
    <TabReaderHead
      :title="title"
      :back-label="backLabel"
      :version="file?.current_version_number ?? null"
      :meta="meta"
      :player="player"
      :has-score="hasScore"
      :profile="profile"
      :profiles="profiles"
      :panel-tab="panel.tab"
      :panel-visible="panelVisible"
      :focused="focused"
      :is-below-md="isBelowMd"
      @back="goBack"
      @update:profile="profile = $event"
      @toggle-panel="togglePanel"
      @focus="setFocused"
      @zoom="changeZoom"
      @browser-full-screen="toggleBrowserFullScreen"
      @download="download"
      @options="optionsOpen = true"
    />

    <div
      v-if="newVersion && hasScore"
      role="status"
      class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 text-sm max-md:m-2 max-md:flex-wrap"
      :class="focused ? 'mx-4 mt-2' : ''"
    >
      <i class="pi pi-refresh text-amber-700 dark:text-amber-300" aria-hidden="true" />
      <span class="flex-1 min-w-0 text-surface-600 dark:text-surface-300">
        <b class="text-surface-900 dark:text-surface-0">{{ t('band_space_tabs.new_version.title') }}</b>
        {{ t('band_space_tabs.new_version.body', { version: newVersion }) }}
      </span>
      <Button :label="t('band_space_tabs.new_version.load', { version: newVersion })" size="small" @click="loadNewVersion" />
      <Button
        icon="pi pi-times"
        text
        rounded
        size="small"
        severity="secondary"
        :aria-label="t('band_space_tabs.new_version.dismiss')"
        @click="newVersion = null"
      />
    </div>

    <div
      class="flex-1 min-h-0 flex flex-col overflow-hidden relative bg-surface-0 dark:bg-surface-900"
      :class="chromeHidden ? '' : 'rounded-2xl border border-surface-200 dark:border-surface-700'"
    >
      <div class="flex-1 min-h-0 flex relative">
        <div class="flex-1 min-w-0 flex relative">
          <!-- Kept in the layout while the file downloads (the loading screen covers it): alphaTab
               drawing into a hidden container ends with no bounds, so clicks and loops on the score
               would do nothing until the next full render. -->
          <AlphaTabScore
            v-show="!failure"
            ref="scoreView"
            class="flex-1 pt-1.5"
            :bytes="bytes"
            :shown-tracks="shownTracks"
            :tracks-for="tracksFor"
            :profile="profile"
            :scale="scale"
            :dark="isDarkMode"
            v-on="player.scoreListeners"
            @loaded="onLoaded"
            @rendered="isRendering = false"
            @unreadable="onUnreadable"
          />

          <div
            v-if="isWaiting"
            class="absolute inset-0 z-[6] flex flex-col gap-7 px-8 py-9 bg-surface-0 dark:bg-surface-900"
            aria-live="polite"
          >
            <p class="m-0 flex items-center justify-center gap-2.5 text-sm text-surface-600 dark:text-surface-300">
              <ProgressSpinner class="w-5! h-5!" stroke-width="6" />{{ t('band_space_tabs.page.loading') }}
            </p>
            <Skeleton v-for="system in 3" :key="system" width="100%" height="4.5rem" />
          </div>

          <div
            v-else-if="failure"
            role="alert"
            class="absolute inset-0 z-[6] flex flex-col items-center justify-center gap-3.5 p-6 text-center bg-surface-0 dark:bg-surface-900"
          >
            <span class="w-14 h-14 rounded-full inline-flex items-center justify-center bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400">
              <i class="pi pi-exclamation-triangle text-2xl" aria-hidden="true" />
            </span>
            <template v-if="failure === FAILURES.UNREADABLE">
              <h2 class="m-0 text-xl font-semibold">{{ t('band_space_tabs.error.unreadable_title') }}</h2>
              <p class="m-0 max-w-[46ch] text-sm text-surface-600 dark:text-surface-300">
                {{ t('band_space_tabs.error.unreadable_body', { name: file?.original_name ?? '' }) }}
              </p>
              <p class="m-0 text-xs text-surface-500 dark:text-surface-400">{{ t('band_space_tabs.error.formats') }}</p>
            </template>
            <template v-else>
              <h2 class="m-0 text-xl font-semibold">{{ t('band_space_tabs.error.unavailable_title') }}</h2>
              <p class="m-0 max-w-[46ch] text-sm text-surface-600 dark:text-surface-300">
                {{ failure === FAILURES.NOT_FOUND ? t('band_space_tabs.error.not_found') : t('band_space_tabs.error.download_failed') }}
              </p>
            </template>
            <div class="flex flex-wrap justify-center gap-2">
              <Button
                v-if="failure === FAILURES.UNREADABLE"
                :label="t('band_space_tabs.options.download')"
                icon="pi pi-download"
                severity="secondary"
                outlined
                @click="download"
              />
              <Button
                v-if="failure === FAILURES.DOWNLOAD"
                :label="t('band_space_tabs.error.retry')"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                @click="loadFile"
              />
              <Button :label="t('band_space_tabs.head.back_to', { name: backLabel })" icon="pi pi-arrow-left" @click="goBack" />
            </div>
          </div>
        </div>

        <TabSidePanel
          v-if="hasScore"
          :player="player"
          :shown="shownTracks"
          :tab="panel.tab"
          :visible="panelVisible"
          :is-below-md="isBelowMd"
          :is-below-lg="isBelowLg"
          @update:tab="showPanel(true, $event)"
          @close="showPanel(false)"
          @toggle-shown="toggleShown"
        />
      </div>

      <TabPlayerBar v-if="hasScore" :player="player" @options="optionsOpen = true" />
    </div>

    <TabOptionsSheet
      v-if="isBelowMd"
      v-model:visible="optionsOpen"
      :player="player"
      :profile="profile"
      :profiles="profiles"
      @update:profile="profile = $event"
      @download="download"
    />
  </div>
</template>

<script setup>
import { useFullscreen, useMediaQuery, useWakeLock } from '@vueuse/core'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'
import TabOptionsSheet from '../../components/BandSpace/Tabs/TabOptionsSheet.vue'
import TabPlayerBar from '../../components/BandSpace/Tabs/TabPlayerBar.vue'
import TabReaderHead from '../../components/BandSpace/Tabs/TabReaderHead.vue'
import TabSidePanel from '../../components/BandSpace/Tabs/TabSidePanel.vue'
import AlphaTabScore from '../../components/Tabs/AlphaTabScore.vue'
import { useBandLayoutFocus } from '../../composables/useBandLayoutFocus.js'
import { useBandSpaceLiveRefresh } from '../../composables/useBandSpaceLiveRefresh.js'
import { COMPACT_NAV_MEDIA_QUERY } from '../../composables/useCompactMenu.js'
import { useDarkMode } from '../../composables/useDarkMode.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { useTabPlayer } from '../../composables/useTabPlayer.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { formatDateCompactWithYear } from '../../utils/date.js'
import { withVersion } from '../../utils/fileVersionUrl.js'
import { guitarProTitle, isGuitarProName } from '../../utils/guitarPro.js'
import { toggleIndex } from '../../utils/tabPlayback.js'
import { tabReaderBackLabel } from '../../utils/tabReaderBack.js'
import {
  openTabReaderStorage,
  readPanel,
  readShownTracks,
  writePanel,
  writeShownTracks
} from '../../utils/tabReaderPrefs.js'

/**
 * The Guitar Pro reader (#1429), opened from a file's drawer: the score, a right panel with the song's
 * structure or its tracks, and a player along the bottom. Testers only for now (TABS_TESTER_ONLY).
 * A new version uploaded meanwhile never replaces the score under the band: a banner offers it.
 */
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const { isDarkMode } = useDarkMode()
const { focused, chromeHidden, setFocused } = useBandLayoutFocus()
const isBelowMd = useIsBelowMd()
// Below lg the panel covers the score instead of sitting beside it.
const isBelowLg = useMediaQuery(COMPACT_NAV_MEDIA_QUERY)
const { toggle: toggleBrowserFullScreen, exit: exitBrowserFullScreen } = useFullscreen(undefined, {
  autoExit: true
})
const wakeLock = useWakeLock()

const FAILURES = Object.freeze({
  NOT_FOUND: 'not_found',
  DOWNLOAD: 'download',
  UNREADABLE: 'unreadable'
})
// The score is drawn smaller on a phone, and 15 % larger in « Plein écran », read from a music stand.
const SCALES = { desktop: 1, tablet: 0.95, phone: 0.7 }
const FOCUS_SCALE = 1.15
const ZOOM = { min: 0.7, max: 1.6, step: 0.1 }

const storage = openTabReaderStorage()

const bandSpaceId = computed(() => route.params.id)
const fileId = computed(() => route.params.fileId)

const scoreView = ref(null)
const player = reactive(
  useTabPlayer(scoreView, {
    onTrainerStep: (speed) =>
      toast.add({
        severity: 'info',
        summary: t('band_space_tabs.speed.trainer_step', { value: Math.round(speed * 100) }),
        life: 2500
      })
  })
)

const file = ref(null)
const bytes = shallowRef(null)
const loadedVersionId = ref(null)
const newVersion = ref(null)
const failure = ref(null)
const isFetching = ref(true)
const isRendering = ref(true)
const shownTracks = ref([0])
const profile = ref('Default')
const zoom = ref(1)
const panel = ref(readPanel(storage))
const overlayOpen = ref(false)
const optionsOpen = ref(false)
let loadedFileId = null

// A score stays mounted while a new version downloads: the loading screen covers it meanwhile.
const hasScore = computed(() => !failure.value && !!bytes.value)
const isWaiting = computed(() => !failure.value && (isFetching.value || isRendering.value))
const title = computed(() => guitarProTitle(file.value?.original_name))
const meta = computed(() => {
  if (!file.value) return ''
  return t('band_space_tabs.head.meta', {
    version: file.value.current_version_number,
    // A file never touched since its upload has no update date yet.
    date: formatDateCompactWithYear(file.value.update_datetime ?? file.value.creation_datetime)
  })
})

// What the score shows, as alphaTab names it, with the short label the phone's switch has room for.
const profiles = computed(() => [
  {
    value: 'Default',
    label: t('band_space_tabs.view.default'),
    short: t('band_space_tabs.view.default')
  },
  {
    value: 'Tab',
    label: t('band_space_tabs.view.tab'),
    short: t('band_space_tabs.view.tab_short')
  },
  {
    value: 'Score',
    label: t('band_space_tabs.view.score'),
    short: t('band_space_tabs.view.score')
  },
  {
    value: 'ScoreTab',
    label: t('band_space_tabs.view.score_tab'),
    short: t('band_space_tabs.view.both_short')
  }
])

const scale = computed(() => {
  const device = isBelowMd.value ? 'phone' : isBelowLg.value ? 'tablet' : 'desktop'
  return Number((SCALES[device] * zoom.value * (focused.value ? FOCUS_SCALE : 1)).toFixed(2))
})

// ---------------------------------------------------------------- the file

let loadRequest = 0

/**
 * Downloads the file's current version; true once its bytes are in. Only the latest call counts: going
 * from one file to another, the first answer arriving last must not replace the second. A reload asked
 * from the new version banner keeps the score on screen when it fails: the band may be playing it.
 */
async function loadFile({ keepScore = false } = {}) {
  const request = ++loadRequest
  const isStale = () => request !== loadRequest
  if (!keepScore) failure.value = null
  isFetching.value = true
  try {
    const loaded = await bandSpaceFilesApi.getFile(bandSpaceId.value, fileId.value)
    if (isStale()) return false
    file.value = loaded
    if (!isGuitarProName(loaded.original_name)) {
      failure.value = FAILURES.UNREADABLE
      return false
    }
    const response = await fetch(withVersion(loaded.download_url, loaded.current_version_id))
    if (!response.ok) throw Object.assign(new Error('download'), { status: response.status })
    const data = await response.arrayBuffer()
    if (isStale()) return false
    isRendering.value = true
    bytes.value = data
    loadedVersionId.value = loaded.current_version_id
    newVersion.value = null
    return true
  } catch (error) {
    if (isStale()) return false
    if (!keepScore || !bytes.value) {
      failure.value = [403, 404].includes(error?.status) ? FAILURES.NOT_FOUND : FAILURES.DOWNLOAD
    }
    return false
  } finally {
    if (!isStale()) isFetching.value = false
  }
}

/** The tracks a score opens on: the ones last shown for this file on this device, or the first. */
function tracksFor(score) {
  return readShownTracks(storage, fileId.value, score.tracks.length) ?? [0]
}

function onLoaded(score, tracks) {
  if (loadedFileId !== fileId.value) player.reset()
  loadedFileId = fileId.value
  player.onLoaded(score)
  shownTracks.value = tracks
}

function onUnreadable() {
  isRendering.value = false
  // A new version that does not open leaves the one on screen: the band may be playing it.
  if (player.score && loadedFileId === fileId.value) {
    toast.add({
      severity: 'error',
      summary: t('band_space_tabs.new_version.unreadable'),
      life: 5000
    })
    return
  }
  failure.value = FAILURES.UNREADABLE
}

async function loadNewVersion() {
  const version = newVersion.value
  const loaded = await loadFile({ keepScore: true })
  toast.add(
    loaded
      ? {
          severity: 'success',
          summary: t('band_space_tabs.new_version.loaded', { version }),
          life: 3000
        }
      : {
          severity: 'error',
          summary: t('band_space_tabs.new_version.failed', { version }),
          life: 5000
        }
  )
}

// A new version, or a version rolled back, raises the banner: the score under the band stays put.
useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['file'],
  refresh: async () => {
    try {
      const latest = await bandSpaceFilesApi.getFile(bandSpaceId.value, fileId.value)
      file.value = latest
      newVersion.value =
        latest.current_version_id !== loadedVersionId.value ? latest.current_version_number : null
    } catch {
      // Quiet: what is on screen stays, the next signal tries again.
    }
  }
})

function download() {
  if (file.value?.download_url) window.open(file.value.download_url, '_blank', 'noopener')
}

// The page that opened the reader, read once: the history entry it came from does not change.
const backLabel = tabReaderBackLabel(
  {
    backLabel: window.history.state?.backLabel,
    previousRouteName: window.history.state?.back
      ? (router.resolve(window.history.state.back).name ?? null)
      : null
  },
  t
)

function goBack() {
  if (window.history.state?.back) {
    router.back()
    return
  }
  router.push({
    name: BAND_SPACE_ROUTES.FILES,
    params: { id: bandSpaceId.value },
    query: { file: fileId.value }
  })
}

watch(fileId, (id) => {
  if (id) loadFile()
})

// ---------------------------------------------------------------- tracks and panel

function toggleShown(index) {
  const shown = toggleIndex(shownTracks.value, index)
  if (shown.length === 0) {
    toast.add({ severity: 'info', summary: t('band_space_tabs.track.keep_one'), life: 2500 })
    return
  }
  shownTracks.value = shown.sort((a, b) => a - b)
  writeShownTracks(storage, fileId.value, shownTracks.value)
}

/** Docked and remembered on a desktop; below it, an overlay that starts closed. */
const panelVisible = computed(() => (isBelowLg.value ? overlayOpen.value : panel.value.visible))

function showPanel(visible, tab = panel.value.tab) {
  if (isBelowLg.value) {
    overlayOpen.value = visible
    panel.value = { ...panel.value, tab }
  } else {
    panel.value = { visible, tab }
  }
  writePanel(storage, panel.value)
}

/** The switch of the panel on screen hides it, the other one shows its own. */
function togglePanel(tab) {
  showPanel(!(panelVisible.value && panel.value.tab === tab), tab)
}

// ---------------------------------------------------------------- full screen and zoom

function changeZoom(direction) {
  const next = zoom.value + direction * ZOOM.step
  zoom.value = Number(Math.min(ZOOM.max, Math.max(ZOOM.min, next)).toFixed(2))
}

// Leaving « Plein écran » leaves the browser's full screen too (and leaving the page, by autoExit).
watch(focused, (on) => {
  if (!on) exitBrowserFullScreen()
})

// ---------------------------------------------------------------- keyboard, pedal, screen awake

function onKeydown(event) {
  if (event.defaultPrevented || !hasScore.value) return
  if (event.target.closest?.('input, select, textarea, [contenteditable="true"], [role="slider"]'))
    return
  const onButton = event.target.closest?.('button, a')
  // Space, or a Bluetooth page-turner pedal (it sends Page down or Page up), plays and pauses.
  if (
    (event.code === 'Space' && !onButton) ||
    event.code === 'PageDown' ||
    event.code === 'PageUp'
  ) {
    event.preventDefault()
    player.playPause()
    return
  }
  // L loops; Ctrl or Cmd with L is the browser's address bar, not ours.
  const plainKey = !event.ctrlKey && !event.metaKey && !event.altKey
  if (plainKey && (event.key === 'l' || event.key === 'L')) player.setLooping(!player.looping)
  if (event.key === 'Escape') {
    if (isBelowLg.value && overlayOpen.value) overlayOpen.value = false
    else if (focused.value) setFocused(false)
  }
}

// The screen stays awake while the band plays; useWakeLock asks again when the tab comes back.
watch(
  () => player.playing,
  (playing) => {
    // A refused lock only means the screen may sleep: playback goes on.
    if (playing) wakeLock.request('screen').catch(() => {})
    else wakeLock.release()
  }
)

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  loadFile()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  setFocused(false)
})
</script>
