/**
 * A Guitar Pro file's structure, read from its markers (Intro, Chorus, Verse...). Takes plain
 * objects shaped like alphaTab's, so it runs without alphaTab:
 * - masterBars: `{ index, section: { marker, text } | null, tempoAutomations: [{ ratioPosition, value }] }`
 * - playback: alphaTab's `tickCache.masterBars`, the bars in playback order (repeats unrolled), each
 *   `{ masterBar: { index }, start, end }` in ticks.
 */

const TICKS_PER_QUARTER = 960

/** The tempo in force at the start of each bar: a change inside a bar shows from the next one. */
export function barTempos(masterBars, scoreTempo) {
  let current = scoreTempo
  return masterBars.map((masterBar) => {
    const changes = masterBar.tempoAutomations ?? []
    const atStart = changes.filter((change) => change.ratioPosition <= 0).pop()
    if (atStart) current = atStart.value
    const shown = current
    if (changes.length) current = changes[changes.length - 1].value
    return shown
  })
}

/**
 * One entry per marker, in written order. A file whose first bar carries no marker gets a leading
 * section with no name (`name: null`), which the reader labels « Début »; so does a file without
 * any marker, as a single section.
 *
 * startTick and endTick span the first time the song plays the section, its own repeats included (an
 * intro written « x3 » loops three times round); a later return to the section is not part of it.
 * seconds is the length of that span.
 */
export function songSections(masterBars, playback, scoreTempo) {
  const sections = []
  for (const masterBar of masterBars) {
    if (masterBar.section) {
      const marker = masterBar.section.marker ?? ''
      sections.push({
        letter: marker,
        name: masterBar.section.text?.trim() || marker || null,
        start: masterBar.index
      })
    }
  }
  if (sections.length === 0 || sections[0].start > 0)
    sections.unshift({ letter: '', name: null, start: 0 })

  const tempos = barTempos(masterBars, scoreTempo)
  return sections.map((section, k) => {
    const end = (sections[k + 1]?.start ?? masterBars.length) - 1
    const first = playback.findIndex((entry) => entry.masterBar.index === section.start)
    let endTick = playback[first]?.end ?? 0
    let seconds = 0
    for (let j = first; j >= 0 && j < playback.length; j++) {
      const index = playback[j].masterBar.index
      if (index < section.start || index > end) break
      endTick = playback[j].end
      seconds += ((playback[j].end - playback[j].start) / TICKS_PER_QUARTER) * (60 / tempos[index])
    }
    return {
      ...section,
      end,
      bars: end - section.start + 1,
      startTick: playback[first]?.start ?? 0,
      endTick,
      seconds
    }
  })
}

export function sectionAtBar(sections, bar) {
  return sections.findIndex((section) => bar >= section.start && bar <= section.end)
}

/**
 * The section a playback range covers exactly, or -1 for any other range. A loop is named from its
 * own ticks: alphaTab can deliver range events after the next loop is already set.
 */
export function sectionForRange(sections, range) {
  if (!range) return -1
  return sections.findIndex(
    (section) => section.startTick === range.startTick && section.endTick === range.endTick
  )
}

// Guitar Pro's usual markers: I intro, A to D parts, O outro. Same letter, same colour, so the two
// choruses of a song match.
const SECTION_HUES = Object.freeze({
  I: 210,
  A: 160,
  B: 335,
  C: 32,
  D: 265,
  E: 190,
  F: 95,
  G: 50,
  O: 210,
  Z: 210
})

/** A hue (0 to 359) for a section's letter; letters outside the usual ones still get a stable one. */
export function sectionHue(letter) {
  const first = (letter ?? '').charAt(0).toUpperCase()
  if (!first) return 210
  return SECTION_HUES[first] ?? (first.charCodeAt(0) * 47) % 360
}
