import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  barTempos,
  sectionAtBar,
  sectionForRange,
  sectionHue,
  songSections
} from './tabSections.js'

const BAR = 3840 // a 4/4 bar in ticks

function bar(index, section = null, tempoAutomations = []) {
  return { index, section, tempoAutomations }
}

/** Playback order for bars written once each, or as listed when the song repeats some. */
function playback(order) {
  return order.map((index, position) => ({
    masterBar: { index },
    start: position * BAR,
    end: (position + 1) * BAR
  }))
}

describe('barTempos', () => {
  it('keeps the score tempo when nothing changes it', () => {
    assert.deepEqual(barTempos([bar(0), bar(1), bar(2)], 140), [140, 140, 140])
  })

  it('applies a change at a bar start to that bar and the following ones', () => {
    const bars = [
      bar(0, null, [{ ratioPosition: 0, value: 120 }]),
      bar(1),
      bar(2, null, [{ ratioPosition: 0, value: 90 }]),
      bar(3)
    ]

    assert.deepEqual(barTempos(bars, 140), [120, 120, 90, 90])
  })

  it('shows a change inside a bar from the next bar', () => {
    const bars = [bar(0), bar(1, null, [{ ratioPosition: 0.5, value: 100 }]), bar(2)]

    assert.deepEqual(barTempos(bars, 140), [140, 140, 100])
  })
})

describe('songSections', () => {
  const marked = [
    bar(0, { marker: 'I', text: 'Intro' }),
    bar(1),
    bar(2, { marker: 'A', text: ' Verse ' }),
    bar(3),
    bar(4, { marker: 'B', text: 'Chorus' }),
    bar(5)
  ]

  it('makes one section per marker, with its bars', () => {
    const sections = songSections(marked, playback([0, 1, 2, 3, 4, 5]), 120)

    assert.deepEqual(
      sections.map(({ letter, name, start, end, bars }) => ({ letter, name, start, end, bars })),
      [
        { letter: 'I', name: 'Intro', start: 0, end: 1, bars: 2 },
        { letter: 'A', name: 'Verse', start: 2, end: 3, bars: 2 },
        { letter: 'B', name: 'Chorus', start: 4, end: 5, bars: 2 }
      ]
    )
  })

  it('spans the first time a section is played, its own repeats included', () => {
    // The verse repeats itself (bars 2 and 3, twice) before the chorus.
    const sections = songSections(marked, playback([0, 1, 2, 3, 2, 3, 4, 5]), 120)

    assert.deepEqual(
      sections.map(({ startTick, endTick, seconds }) => ({ startTick, endTick, seconds })),
      [
        { startTick: 0, endTick: 2 * BAR, seconds: 4 },
        { startTick: 2 * BAR, endTick: 6 * BAR, seconds: 8 },
        { startTick: 6 * BAR, endTick: 8 * BAR, seconds: 4 }
      ]
    )
  })

  it('leaves a later return to a section out of its span', () => {
    // Verse, chorus, then the verse again: the verse loops its first time only.
    const sections = songSections(marked, playback([0, 1, 2, 3, 4, 5, 2, 3]), 120)

    assert.deepEqual([sections[1].startTick, sections[1].endTick], [2 * BAR, 4 * BAR])
  })

  it('counts the length with the tempo of each bar', () => {
    const bars = [
      bar(0, { marker: 'I', text: 'Intro' }, [{ ratioPosition: 0, value: 60 }]),
      bar(1, null, [{ ratioPosition: 0, value: 120 }])
    ]

    assert.equal(songSections(bars, playback([0, 1]), 140)[0].seconds, 6)
  })

  it('opens with an unnamed section when the first bar has no marker', () => {
    const bars = [bar(0), bar(1), bar(2, { marker: 'B', text: 'Chorus' })]
    const sections = songSections(bars, playback([0, 1, 2]), 120)

    assert.deepEqual(
      sections.map(({ letter, name, start, end }) => ({ letter, name, start, end })),
      [
        { letter: '', name: null, start: 0, end: 1 },
        { letter: 'B', name: 'Chorus', start: 2, end: 2 }
      ]
    )
  })

  it('makes the whole song one unnamed section when the file has no marker', () => {
    const sections = songSections([bar(0), bar(1)], playback([0, 1]), 120)

    assert.equal(sections.length, 1)
    assert.deepEqual(
      [sections[0].name, sections[0].start, sections[0].end, sections[0].endTick],
      [null, 0, 1, 2 * BAR]
    )
  })

  it('names a section by its marker when it has no text', () => {
    const sections = songSections([bar(0, { marker: 'C', text: '' })], playback([0]), 120)

    assert.equal(sections[0].name, 'C')
  })
})

describe('sectionAtBar', () => {
  const sections = songSections(
    [bar(0, { marker: 'I', text: 'Intro' }), bar(1), bar(2, { marker: 'A', text: 'Verse' })],
    playback([0, 1, 2]),
    120
  )

  it('finds the section a bar belongs to', () => {
    assert.equal(sectionAtBar(sections, 0), 0)
    assert.equal(sectionAtBar(sections, 1), 0)
    assert.equal(sectionAtBar(sections, 2), 1)
  })

  it('answers -1 outside the song', () => {
    assert.equal(sectionAtBar(sections, 7), -1)
  })
})

describe('sectionForRange', () => {
  const sections = songSections(
    [bar(0, { marker: 'I', text: 'Intro' }), bar(1, { marker: 'A', text: 'Verse' }), bar(2)],
    playback([0, 1, 2]),
    120
  )

  it('names the section a range covers exactly', () => {
    assert.equal(sectionForRange(sections, { startTick: BAR, endTick: 3 * BAR }), 1)
  })

  it('answers -1 for any other range, and for no range', () => {
    assert.equal(sectionForRange(sections, { startTick: BAR, endTick: 2 * BAR }), -1)
    assert.equal(sectionForRange(sections, null), -1)
  })
})

describe('sectionHue', () => {
  it('gives both choruses the same colour', () => {
    assert.equal(sectionHue('B'), sectionHue('B'))
    assert.notEqual(sectionHue('A'), sectionHue('B'))
  })

  it('gives letters outside the usual ones a stable hue, and a default to an empty one', () => {
    assert.equal(sectionHue('Q'), sectionHue('q'))
    assert.ok(sectionHue('Q') >= 0 && sectionHue('Q') < 360)
    assert.equal(sectionHue(''), 210)
    assert.equal(sectionHue(null), 210)
  })
})
