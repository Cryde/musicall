<template>
  <div
    ref="scrollEl"
    :class="['tab-score relative bg-surface-0 dark:bg-surface-900', preview ? 'overflow-hidden' : 'overflow-y-auto overflow-x-hidden']"
  >
    <div ref="scoreEl" class="min-h-full" />
  </div>
</template>

<script>
import {
  ALPHATAB_FONT_URL,
  GENERAL_USER_MANIFEST,
  GENERAL_USER_URL
} from '../../constants/tabReader.js'
import { createSoundFontLoader } from '../../utils/tabSoundLoader.js'

// One for the page's lifetime: a song opened again, or another with the same instruments, downloads
// nothing.
const soundFonts = createSoundFontLoader({
  baseUrl: GENERAL_USER_URL,
  manifestName: GENERAL_USER_MANIFEST
})
</script>

<script setup>
import { onBeforeUnmount, onMounted, shallowRef, watch } from 'vue'
import { MELODIC_BANK, soundFontFilesFor } from '../../utils/tabSounds.js'

/**
 * Draws and plays a Guitar Pro file with alphaTab (#1427). A file goes in, a score comes out: no Band
 * Space code here, so the Tablatures module, MusicAll Studio and the later editor reuse it. The player
 * state (speed, loops, mute...) belongs to useTabPlayer, which drives the API this exposes.
 *
 * alphaTab is about 300 kB gzipped, so it is imported when the first score mounts, not with the app.
 * Its sounds are GeneralUser GS split per instrument at build time: only the song's ones download.
 */
const props = defineProps({
  /** The file's bytes; a new value loads another score. */
  bytes: { type: [ArrayBuffer, Uint8Array], default: null },
  /** Indexes of the tracks drawn. */
  shownTracks: { type: Array, default: () => [0] },
  /** The tracks a new score opens on, from the score: the ones the member last showed, say. */
  tracksFor: { type: Function, default: null },
  /** alphaTab's StaveProfile name: Default (as saved in the file), Tab, Score or ScoreTab. */
  profile: { type: String, default: 'Default' },
  scale: { type: Number, default: 1 },
  dark: { type: Boolean, default: false },
  /** A song page's glimpse (#1431): the first `barCount` bars, no player, no sounds downloaded. */
  preview: { type: Boolean, default: false },
  barCount: { type: Number, default: 4 }
})

const emit = defineEmits([
  'loaded',
  'rendered',
  'unreadable',
  'sound-progress',
  'sound-ready',
  'sound-error',
  'timeline',
  'playing',
  'position',
  'bar',
  'range',
  'finished'
])

const INK = {
  light: {
    mainGlyphColor: '#111827',
    secondaryGlyphColor: '#11182799',
    staffLineColor: '#94a3b8',
    barSeparatorColor: '#334155',
    barNumberColor: '#64748b',
    scoreInfoColor: '#111827'
  },
  dark: {
    mainGlyphColor: '#e4e4e7',
    secondaryGlyphColor: '#e4e4e799',
    staffLineColor: '#52525b',
    barSeparatorColor: '#a1a1aa',
    barNumberColor: '#8b8b93',
    scoreInfoColor: '#e4e4e7'
  }
}
// alphaTab reports a SoundFont load once per file, and sometimes twice: the sounds count as ready once
// the events have been quiet this long.
const SOUND_SETTLE_MS = 250

const scrollEl = shallowRef(null)
const scoreEl = shallowRef(null)
const api = shallowRef(null)
let alphaTab = null
let score = null
let pendingTick = null
let renderedTracks = []
let destroyed = false

function ink() {
  return INK[props.dark ? 'dark' : 'light']
}

const isPercussion = (track) => track.staves.some((staff) => staff.isPercussion)

// What a score draws above its first bars. A preview is a few lines high: without these, it shows the
// file's tuning and chord diagrams rather than the tab.
const HEADER_ELEMENTS = [
  'ScoreTitle',
  'ScoreSubTitle',
  'ScoreArtist',
  'ScoreAlbum',
  'ScoreWords',
  'ScoreMusic',
  'ScoreWordsAndMusic',
  'ScoreCopyright',
  'GuitarTuning',
  'TrackNames',
  'ChordDiagrams'
]

function hideScoreHeader(instance) {
  for (const name of HEADER_ELEMENTS) {
    instance.settings.notation.elements.set(alphaTab.NotationElement[name], false)
  }
  instance.updateSettings()
}

async function create() {
  // Asked for now, so the sounds can start as soon as the score is parsed.
  if (!props.preview) soundFonts.manifest().catch(() => {})
  alphaTab = await import('@coderline/alphatab')
  if (destroyed) return
  const instance = new alphaTab.AlphaTabApi(scoreEl.value, {
    // A preview draws a few bars: not worth a worker loading alphaTab a second time.
    core: { fontDirectory: ALPHATAB_FONT_URL, logLevel: alphaTab.LogLevel.Error, useWorkers: !props.preview },
    display: {
      layoutMode: alphaTab.LayoutMode.Page,
      scale: props.scale,
      staveProfile: alphaTab.StaveProfile[props.profile],
      resources: ink(),
      ...(props.preview ? { barCount: props.barCount } : {})
    },
    player: props.preview ? { playerMode: alphaTab.PlayerMode.Disabled } : {
      playerMode: alphaTab.PlayerMode.EnabledSynthesizer,
      soundFont: null,
      scrollElement: scrollEl.value,
      scrollMode: alphaTab.ScrollMode.Continuous,
      scrollOffsetY: -24,
      enableCursor: true,
      enableAnimatedBeatCursor: true,
      enableElementHighlighting: true,
      enableSeekToClick: true,
      enablePlaybackRangeSelection: true
    }
  })
  if (props.preview) hideScoreHeader(instance)
  listen(instance)
  api.value = instance
  // An iPhone on silent plays no Web Audio unless the page asks for media playback.
  if (navigator.audioSession) navigator.audioSession.type = 'playback'
  if (props.bytes) load(props.bytes)
}

function listen(instance) {
  instance.playerReady.on(() => {
    if (pendingTick !== null) {
      instance.tickPosition = pendingTick
      pendingTick = null
    }
  })
  instance.midiLoaded.on(() => emit('timeline', instance.tickCache))
  instance.playerStateChanged.on((event) =>
    emit('playing', event.state === alphaTab.synth.PlayerState.Playing)
  )
  instance.playerPositionChanged.on((event) => {
    emit('position', event)
    if (event.isSeek || instance.playerState !== alphaTab.synth.PlayerState.Playing) {
      const hit = instance.tickCache?.findBeat(new Set(props.shownTracks), event.currentTick)
      if (hit?.beat) emit('bar', hit.beat.voice.bar.index)
    }
  })
  instance.playedBeatChanged.on((beat) => emit('bar', beat.voice.bar.index))
  instance.playbackRangeChanged.on((event) => emit('range', event.playbackRange))
  // alphaTab draws its kept selection again after each render, on the beats it was made on: shown on
  // other tracks, those are not drawn any more and it throws. Shade the loop on what is drawn now.
  instance.renderFinished.on(() => {
    emit('rendered')
    shadeLoop()
  })
  instance.playerFinished.on(() => emit('finished'))
  instance.player?.soundFontLoaded.on(onSoundFontLoaded)
  instance.player?.soundFontLoadFailed.on(() => {
    soundsPosted = 0
    emit('sound-error')
  })
}

/**
 * Prepares a file for drawing and playing:
 * - a drum track saved as tab would be drawn as MIDI note numbers (36, 38, 46), so its staves switch
 *   to notation;
 * - only GeneralUser's main bank ships, and alphaTab finds no sound for a melodic track saved with a
 *   variation bank, so those play bank 0.
 */
function prepare(loaded) {
  for (const track of loaded.tracks) {
    for (const staff of track.staves) {
      if (staff.isPercussion) {
        staff.showTablature = false
        staff.showStandardNotation = true
      }
    }
    if (!isPercussion(track)) track.playbackInfo.bank = MELODIC_BANK
  }
}

function load(bytes) {
  const instance = api.value
  if (!instance) return
  if (instance.playerState === alphaTab.synth.PlayerState.Playing) instance.stop()
  let loaded
  try {
    loaded = alphaTab.importer.ScoreLoader.loadScoreFromBytes(
      new Uint8Array(bytes),
      instance.settings
    )
  } catch (error) {
    emit('unreadable', error)
    return
  }
  prepare(loaded)
  score = loaded
  setPlaybackRange(null)
  const tracks =
    props.tracksFor?.(loaded) ?? props.shownTracks.filter((index) => index < loaded.tracks.length)
  renderedTracks = tracks.length ? tracks : [0]
  instance.renderScore(loaded, renderedTracks)
  emit('loaded', loaded, renderedTracks)
  if (!props.preview) loadSounds(loaded)
}

/**
 * Drops a selection for good. alphaTab 1.9's clearPlaybackRangeHighlight() only erases the drawing: the
 * selection it keeps is drawn again after every render (zoom, resize, other tracks) and throws once it
 * belongs to another song. There is no public way to drop it, hence the two private fields. Remove
 * once a release fixes https://github.com/CoderLine/alphaTab/issues/3042.
 */
function forgetSelection() {
  const instance = api.value
  if (!instance) return
  // Guarded: a release that renames them makes this a plain clear, not a stray property.
  if ('_selectionStart' in instance) instance._selectionStart = undefined
  if ('_selectionEnd' in instance) instance._selectionEnd = undefined
  instance.clearPlaybackRangeHighlight()
}

// ---------------------------------------------------------------- sounds

let soundsKey = null
let soundGeneration = 0
let soundsPosted = 0
let settleTimer = null

async function loadSounds(loaded) {
  const generation = ++soundGeneration
  try {
    const { presets } = await soundFonts.manifest()
    const tracks = loaded.tracks.map((track) => ({
      program: track.playbackInfo.program,
      isPercussion: isPercussion(track)
    }))
    const files = soundFontFilesFor(tracks, presets)
    if (files.length === 0) throw new Error('No sound for these instruments')
    const key = files.map((file) => file.file).join(',')
    if (key === soundsKey) {
      emit('sound-ready')
      return
    }
    emit('sound-progress', 0)
    const bytes = await soundFonts.download(files, (ratio) => {
      if (generation === soundGeneration) emit('sound-progress', ratio)
    })
    if (generation !== soundGeneration || !api.value) return
    soundsKey = key
    soundsPosted = generation
    bytes.forEach((data, k) => {
      api.value.loadSoundFont(data, k > 0)
    })
  } catch {
    if (generation === soundGeneration) emit('sound-error')
  }
}

/** After a failed download: the requests that failed are forgotten, so this asks for them again. */
function retrySounds() {
  if (!score) return
  soundsKey = null
  loadSounds(score)
}

function onSoundFontLoaded() {
  if (!soundsPosted) return
  clearTimeout(settleTimer)
  settleTimer = setTimeout(() => {
    if (soundsPosted !== soundGeneration) return
    soundsPosted = 0
    emit('sound-ready')
  }, SOUND_SETTLE_MS)
}

// ---------------------------------------------------------------- what the parent drives

function rerender() {
  const instance = api.value
  if (!instance) return
  const display = instance.settings.display
  display.scale = props.scale
  display.staveProfile = alphaTab.StaveProfile[props.profile]
  for (const [key, value] of Object.entries(ink()))
    display.resources[key] = alphaTab.model.Color.fromJson(value)
  instance.updateSettings()
  instance.render()
}

/** A seek made before the sounds are in is dropped by alphaTab: it waits for playerReady. */
function seekTick(tick) {
  const instance = api.value
  if (!instance) return
  if (instance.isReadyForPlayback) instance.tickPosition = tick
  else pendingTick = tick
}

/**
 * Loops the ticks from startTick to endTick, or lets go of the loop with null: the one way the range
 * is set or cleared, so the shading and the workaround in forgetSelection() always go with it.
 */
function setPlaybackRange(startTick, endTick) {
  const instance = api.value
  if (!instance) return
  if (startTick === null) {
    instance.playbackRange = null
    forgetSelection()
    return
  }
  const range = new alphaTab.synth.PlaybackRange()
  range.startTick = startTick
  range.endTick = endTick
  instance.playbackRange = range
  shadeLoop()
}

/** Shades the loop on the tracks drawn now, or drops any shading when nothing loops. */
function shadeLoop() {
  const instance = api.value
  const range = instance?.playbackRange
  if (!range) return forgetSelection()
  const shown = new Set(props.shownTracks)
  const first = instance.tickCache?.findBeat(shown, range.startTick)?.beat
  const last = instance.tickCache?.findBeat(shown, Math.max(range.startTick, range.endTick - 1))?.beat
  if (first && last) instance.highlightPlaybackRange(first, last)
}

watch(
  () => props.bytes,
  (bytes) => {
    if (bytes) load(bytes)
  }
)
watch(
  () => props.shownTracks,
  (shown) => {
    if (!api.value || !score || sameTracks(shown, renderedTracks)) return
    renderedTracks = [...shown]
    api.value.renderTracks(shown.map((index) => score.tracks[index]))
  }
)

function sameTracks(a, b) {
  return a.length === b.length && a.every((index, k) => index === b[k])
}
watch(() => [props.profile, props.scale, props.dark], rerender)

onMounted(create)

onBeforeUnmount(() => {
  destroyed = true
  clearTimeout(settleTimer)
  api.value?.destroy()
  api.value = null
  // Megabytes of samples: no reader left to play them, the browser cache serves them next time.
  soundFonts.forget()
})

defineExpose({ api, seekTick, setPlaybackRange, retrySounds })
</script>

<style>
/* alphaTab's cursor and selection layers. isolation keeps them under whatever floats over the score. */
.tab-score {
  isolation: isolate;
}
.tab-score .at-cursor-bar {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.tab-score .at-cursor-beat {
  background: var(--p-primary-color);
  width: 3px;
  border-radius: 2px;
}
.tab-score .at-selection div {
  background: color-mix(in srgb, var(--p-primary-color) 18%, transparent);
}
.tab-score .at-highlight * {
  fill: var(--p-primary-color);
  stroke: var(--p-primary-color);
}
</style>
