import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'
import {
  assignRange,
  fromChordSite,
  parse,
  parseLine,
  SECTION_KINDS,
  SINGER_ALL_COLOR,
  SINGER_PALETTE,
  serialize,
  singerColors,
  singerIds,
  singerTint,
  toSheet,
  transposeChord,
  transposeKey,
  transposeSource
} from './chordpro.js'

// Shared with the PHP parser's test, so the drawer and the PDF read a song the same way.
const cases = JSON.parse(
  readFileSync(new URL('../../../tests/Fixtures/ChordPro/cases.json', import.meta.url))
)

const LEA = '3f2504e0-4f89-41d3-9a0c-0305e82c3301'
const TOM = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'

describe('chordpro shared cases', () => {
  it('knows the same parts as the PDF', () => {
    assert.deepEqual(SECTION_KINDS, cases.section_kinds)
  })

  it('colours singers from the shared palette', () => {
    assert.deepEqual(SINGER_PALETTE, cases.palette.singers)
    assert.equal(SINGER_ALL_COLOR, cases.palette.all)
  })

  for (const { source, expected } of cases.sheets) {
    it(`reads ${JSON.stringify(source).slice(0, 50)}`, () => {
      assert.deepEqual(toSheet(parse(source)), expected)
    })
  }

  for (const { source, expected } of cases.singers) {
    it(`lists the singers of ${JSON.stringify(source).slice(0, 40)}`, () => {
      assert.deepEqual(singerIds(parse(source)), expected)
    })
  }

  for (const { chord, steps, flats, expected } of cases.chords) {
    it(`transposes ${chord} by ${steps}`, () => {
      assert.equal(transposeChord(chord, steps, flats), expected)
    })
  }

  for (const { key, steps, expected } of cases.keys) {
    it(`transposes the key ${key} by ${steps}`, () => {
      assert.equal(transposeKey(key, steps), expected)
    })
  }

  for (const { source, steps, key, expected } of cases.sources) {
    it(`transposes a source by ${steps} from ${key}`, () => {
      assert.equal(transposeSource(source, steps, key), expected)
    })
  }
})

describe('singerColors', () => {
  it('colours by order of first appearance, and « Tous » apart', () => {
    assert.deepEqual(singerColors([TOM, 'all', LEA]), {
      [TOM]: SINGER_PALETTE[0],
      all: SINGER_ALL_COLOR,
      [LEA]: SINGER_PALETTE[1]
    })
  })
})

describe('singerTint', () => {
  it('fades one singer and stripes several', () => {
    assert.equal(singerTint([LEA], { [LEA]: '#4f46e5' }), '#4f46e533')
    assert.equal(
      singerTint([LEA, TOM], { [LEA]: '#4f46e5', [TOM]: '#b45309' }),
      'linear-gradient(180deg, #4f46e540 0% 50%, #b4530940 50% 100%)'
    )
  })
})

describe('serialize', () => {
  it('round trips a canonical song', () => {
    const source = [
      '{start_of_verse: Couplet 1}',
      `[C]Au clair de la [G]lune, <span singer="@[${LEA}]">mon [C]ami</span> Pierrot`,
      '{comment: Pause}',
      '{end_of_verse}',
      '{start_of_chorus}',
      '<span singer="all">[G]Ma chandelle</span>',
      '{end_of_chorus}',
      '{title: Au clair}'
    ].join('\n')

    assert.equal(serialize(parse(source)), source)
  })

  it('writes the short forms back in long form', () => {
    assert.equal(
      serialize(parse('{soc}\nLa\n{eoc}\n{c: Pause}')),
      '{start_of_chorus}\nLa\n{end_of_chorus}\n{comment: Pause}'
    )
  })

  it('puts a chord inside the range it starts', () => {
    const line = {
      type: 'line',
      text: 'other side',
      chords: [{ pos: 0, name: 'G' }],
      ranges: [{ start: 0, end: 5, singers: [TOM] }]
    }

    assert.equal(serialize([line]), `<span singer="@[${TOM}]">[G]other</span> side`)
  })
})

describe('assignRange', () => {
  const line = parseLine(`Hello <span singer="@[${LEA}]">from the other</span> side`)

  it('cuts an existing range around the new one', () => {
    const next = assignRange(line, 11, 14, [TOM])

    assert.equal(
      serialize([next]),
      `Hello <span singer="@[${LEA}]">from </span><span singer="@[${TOM}]">the</span><span singer="@[${LEA}]"> other</span> side`
    )
  })

  it('removes an assignment with no singers', () => {
    assert.equal(serialize([assignRange(line, 0, 25, [])]), 'Hello from the other side')
  })

  it('joins a neighbour sung by the same people', () => {
    assert.equal(
      serialize([assignRange(line, 20, 25, [LEA])]),
      `Hello <span singer="@[${LEA}]">from the other side</span>`
    )
  })

  it('ignores an empty selection', () => {
    assert.equal(assignRange(line, 3, 3, [TOM]), line)
  })
})

describe('fromChordSite', () => {
  it('merges a chord line into the lyric under it, by column', () => {
    assert.equal(
      fromChordSite('C              G\nAu clair de la lune'),
      '[C]Au clair de la [G]lune'
    )
  })

  it('pads a lyric shorter than its chords', () => {
    assert.equal(fromChordSite('C     G      Am\nAu clair'), '[C]Au cla[G]ir     [Am]')
  })

  it('turns headers into sections, in French', () => {
    assert.equal(
      fromChordSite('[Verse 1]\nC\nLa lune\n[Chorus]\nG\nMa chandelle'),
      '{start_of_verse: Couplet 1}\n[C]La lune\n{end_of_verse}\n{start_of_chorus}\n[G]Ma chandelle\n{end_of_chorus}'
    )
  })

  it('reads every kind of part a chord site names', () => {
    assert.equal(
      fromChordSite('[Intro]\n[Pre-Chorus]\n[Breakdown]\n[Break]\n[Solo 2]\nOutro:'),
      [
        '{start_of_intro}',
        '{end_of_intro}',
        '{start_of_prechorus}',
        '{end_of_prechorus}',
        '{start_of_break: Breakdown}',
        '{end_of_break}',
        '{start_of_break}',
        '{end_of_break}',
        '{start_of_solo: Solo 2}',
        '{end_of_solo}',
        '{start_of_outro}',
        '{end_of_outro}'
      ].join('\n')
    )
  })

  it('keeps an instrumental chord line and skips the bar marks', () => {
    assert.equal(fromChordSite('| Am  | G  |'), '  [Am]      [G]')
  })

  it('reads French chord names', () => {
    assert.equal(fromChordSite('Do       Sol\nAu clair de lune'), '[Do]Au clair [Sol]de lune')
  })

  it('leaves ChordPro and plain lyrics alone', () => {
    const source = '{soc}\n[Am]Hello from the [G]other side\nSi la vie\n{eoc}'

    assert.equal(fromChordSite(source), source)
  })
})
