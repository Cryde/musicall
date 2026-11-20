import { computed, ref, shallowRef } from 'vue'
import { clampSpeed, SPEED, toggleIndex, trainerNextSpeed } from '../utils/tabPlayback.js'
import { barTempos, sectionAtBar, sectionForRange, songSections } from '../utils/tabSections.js'

/**
 * The player of a tab: speed, loops (the whole song, a section, or a dragged passage), metronome,
 * count-in, the speed trainer, and mute, solo and volume per track. Drives the API of an AlphaTabScore
 * (`scoreView` is its template ref) and listens to it through `scoreListeners`, bound in one go with
 * `v-on`. No Band Space code, so the Tablatures module and Studio can reuse it.
 */
export function useTabPlayer(scoreView, { onTrainerStep = () => {} } = {}) {
  const api = () => scoreView.value?.api ?? null

  const score = shallowRef(null)
  const sections = shallowRef([])
  const tempos = shallowRef([])
  const soundReady = ref(false)
  const soundProgress = ref(0)
  const soundFailed = ref(false)
  const playing = ref(false)
  // In whole seconds: alphaTab reports the position about every 10 ms, the clock shows seconds.
  const time = ref(0)
  const duration = ref(0)
  const bar = ref(0)
  const speed = ref(1)
  const looping = ref(false)
  const metronome = ref(false)
  const countIn = ref(false)
  const trainer = ref(false)
  const muted = ref([])
  const soloed = ref([])
  const volumes = ref({})
  const range = shallowRef(null)

  const loopedSection = computed(() => sectionForRange(sections.value, range.value))
  /** The first and last bar (0-based) a range covers, for the loop chip. */
  const rangeBars = computed(() => {
    const playbackRange = range.value
    if (!playbackRange) return null
    return {
      first: barAtTick(playbackRange.startTick),
      last: barAtTick(Math.max(playbackRange.startTick, playbackRange.endTick - 1))
    }
  })
  const currentSection = computed(() => sectionAtBar(sections.value, bar.value))
  const barCount = computed(() => score.value?.masterBars.length ?? 0)
  /** For information: the tempo the bar is written at, and the one heard at the current speed. */
  const writtenTempo = computed(() =>
    Math.round(tempos.value[bar.value] ?? score.value?.tempo ?? 0)
  )
  const heardTempo = computed(() => Math.round(writtenTempo.value * speed.value))
  const timeSignature = computed(() => {
    const masterBar = score.value?.masterBars[bar.value]
    return masterBar
      ? `${masterBar.timeSignatureNumerator}/${masterBar.timeSignatureDenominator}`
      : ''
  })

  function barAtTick(tick) {
    const entry = api()?.tickCache?.masterBars.find((item) => tick >= item.start && tick < item.end)
    return entry?.masterBar.index ?? 0
  }

  const trackAt = (index) => score.value?.tracks[index]

  /** The whole mix at once, for when the sounds arrive; a change afterwards sends only itself. */
  function applyAudio() {
    const instance = api()
    if (!instance) return
    instance.playbackSpeed = speed.value
    instance.isLooping = looping.value
    instance.metronomeVolume = metronome.value ? 1 : 0
    instance.countInVolume = countIn.value ? 1 : 0
    const tracks = score.value?.tracks ?? []
    if (!tracks.length) return
    instance.changeTrackMute(tracks, false)
    instance.changeTrackSolo(tracks, false)
    instance.changeTrackMute(
      tracks.filter((track) => muted.value.includes(track.index)),
      true
    )
    instance.changeTrackSolo(
      tracks.filter((track) => soloed.value.includes(track.index)),
      true
    )
    for (const track of tracks) instance.changeTrackVolume([track], volumes.value[track.index] ?? 1)
  }

  // ------------------------------------------------------------ the score's events

  function onLoaded(loaded) {
    score.value = loaded
    sections.value = []
    tempos.value = barTempos(loaded.masterBars, loaded.tempo)
    bar.value = 0
    range.value = null
    looping.value = false
    const kept = (indexes) => indexes.filter((index) => index < loaded.tracks.length)
    muted.value = kept(muted.value)
    soloed.value = kept(soloed.value)
  }

  /** alphaTab's playback map is ready: the sections get their ticks, repeats included. */
  function onTimeline(tickCache) {
    if (!score.value || !tickCache) return
    sections.value = songSections(score.value.masterBars, tickCache.masterBars, score.value.tempo)
  }

  function onSoundProgress(ratio) {
    soundReady.value = false
    soundFailed.value = false
    // Whole percents: the bar redraws a hundred times at most, not once per network chunk.
    soundProgress.value = Math.round(ratio * 100) / 100
  }

  function onSoundReady() {
    soundReady.value = true
    applyAudio()
  }

  function onSoundError() {
    soundFailed.value = true
  }

  function retrySounds() {
    soundFailed.value = false
    soundProgress.value = 0
    scoreView.value?.retrySounds()
  }

  function onPlaying(isPlaying) {
    playing.value = isPlaying
  }

  function onPosition(event) {
    time.value = Math.floor(event.currentTime / 1000)
    duration.value = Math.floor(event.endTime / 1000)
  }

  function onBar(index) {
    bar.value = index
  }

  /** Any range loops; no range ends the loop. The section it covers is read from the range itself. */
  function onRange(playbackRange) {
    range.value = playbackRange
    setLooping(!!playbackRange)
  }

  function onFinished() {
    const next = trainerNextSpeed(speed.value, { trainer: trainer.value, looping: looping.value })
    if (next === null) return
    setSpeed(next)
    onTrainerStep(next)
  }

  // ------------------------------------------------------------ actions

  function playPause() {
    if (soundReady.value) api()?.playPause()
  }

  function stop() {
    api()?.stop()
  }

  function restart() {
    scoreView.value?.seekTick(range.value?.startTick ?? 0)
  }

  function setSpeed(value) {
    speed.value = clampSpeed(value)
    const instance = api()
    if (instance) instance.playbackSpeed = speed.value
  }

  function setLooping(on) {
    looping.value = on
    const instance = api()
    if (instance) instance.isLooping = on
  }

  function toggleMetronome() {
    metronome.value = !metronome.value
    const instance = api()
    if (instance) instance.metronomeVolume = metronome.value ? 1 : 0
  }

  function toggleCountIn() {
    countIn.value = !countIn.value
    const instance = api()
    if (instance) instance.countInVolume = countIn.value ? 1 : 0
  }

  function toggleTrainer() {
    trainer.value = !trainer.value
    if (trainer.value && speed.value >= SPEED.MAX) setSpeed(SPEED.TRAINER_START)
  }

  function toggleMute(index) {
    muted.value = toggleIndex(muted.value, index)
    const track = trackAt(index)
    if (track) api()?.changeTrackMute([track], muted.value.includes(index))
  }

  function toggleSolo(index) {
    soloed.value = toggleIndex(soloed.value, index)
    const track = trackAt(index)
    if (track) api()?.changeTrackSolo([track], soloed.value.includes(index))
  }

  function setVolume(index, value) {
    volumes.value[index] = value
    const track = trackAt(index)
    if (track) api()?.changeTrackVolume([track], value)
  }

  function goToSection(k) {
    const section = sections.value[k]
    if (!section) return
    scoreView.value?.seekTick(section.startTick)
    bar.value = section.start
  }

  /** Loops a section, or lets go of it when it is the one looping. */
  function loopSection(k) {
    const section = sections.value[k]
    if (!api() || !section) return
    if (loopedSection.value === k) return clearRange()
    scoreView.value.setPlaybackRange(section.startTick, section.endTick)
    range.value = api().playbackRange
    setLooping(true)
    goToSection(k)
  }

  function clearRange() {
    if (!api()) return
    scoreView.value.setPlaybackRange(null)
    range.value = null
    setLooping(false)
  }

  /** Back to a fresh player, for another file: the tracks of one song mean nothing in the next. */
  function reset() {
    muted.value = []
    soloed.value = []
    volumes.value = {}
    trainer.value = false
    metronome.value = false
    countIn.value = false
    setSpeed(SPEED.MAX)
    if (range.value) clearRange()
    applyAudio()
  }

  /** What an AlphaTabScore reports, for `v-on`: the page adds its own `loaded` on top. */
  const scoreListeners = {
    timeline: onTimeline,
    'sound-progress': onSoundProgress,
    'sound-ready': onSoundReady,
    'sound-error': onSoundError,
    playing: onPlaying,
    position: onPosition,
    bar: onBar,
    range: onRange,
    finished: onFinished
  }

  return {
    score,
    sections,
    soundReady,
    soundProgress,
    soundFailed,
    playing,
    time,
    duration,
    bar,
    barCount,
    speed,
    looping,
    metronome,
    countIn,
    trainer,
    muted,
    soloed,
    volumes,
    range,
    loopedSection,
    rangeBars,
    currentSection,
    writtenTempo,
    heardTempo,
    timeSignature,
    scoreListeners,
    onLoaded,
    retrySounds,
    playPause,
    stop,
    restart,
    setSpeed,
    setLooping,
    toggleMetronome,
    toggleCountIn,
    toggleTrainer,
    toggleMute,
    toggleSolo,
    setVolume,
    goToSection,
    loopSection,
    clearRange,
    reset
  }
}
