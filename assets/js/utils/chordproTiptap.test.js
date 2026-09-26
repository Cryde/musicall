import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { parse, serialize } from './chordpro.js'
import { fromDoc, toDoc } from './chordproTiptap.js'

const LEA = '3f2504e0-4f89-41d3-9a0c-0305e82c3301'
const TOM = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'

const roundTrip = (source) => serialize(fromDoc(toDoc(parse(source))))

describe('chordproTiptap', () => {
  it('round trips a song through the editor document', () => {
    const source = [
      '{start_of_verse: Couplet 1}',
      `[C]Au clair de la [G]lune, <span singer="@[${LEA}]">mon [C]ami</span> Pierrot`,
      '',
      '{comment: Pause}',
      '{end_of_verse}',
      '{start_of_chorus}',
      `<span singer="@[${LEA}] @[${TOM}]">[G]Ma chandelle</span><span singer="all">est morte</span>`,
      '{end_of_chorus}',
      '{title: Au clair}',
      'Fin[C]'
    ].join('\n')

    assert.equal(roundTrip(source), source)
  })

  it('makes a chord an atom and a singer range a mark', () => {
    assert.deepEqual(toDoc(parse(`[Am]Hello <span singer="@[${LEA}]">wor[G]ld</span>`)), {
      type: 'doc',
      content: [
        {
          type: 'lyricsLine',
          content: [
            { type: 'chord', attrs: { name: 'Am' } },
            { type: 'text', text: 'Hello ' },
            { type: 'text', text: 'wor', marks: [{ type: 'singer', attrs: { singers: [LEA] } }] },
            { type: 'chord', attrs: { name: 'G' } },
            { type: 'text', text: 'ld', marks: [{ type: 'singer', attrs: { singers: [LEA] } }] }
          ]
        }
      ]
    })
  })

  it('gives an empty song and an empty section a line to type in', () => {
    assert.deepEqual(toDoc(parse('')), { type: 'doc', content: [{ type: 'lyricsLine' }] })
    assert.deepEqual(toDoc(parse('{soc}\n{eoc}')).content[0].content, [{ type: 'lyricsLine' }])
  })

  it('keeps two neighbouring ranges of different singers apart', () => {
    const doc = {
      type: 'doc',
      content: [
        {
          type: 'lyricsLine',
          content: [
            { type: 'text', text: 'a', marks: [{ type: 'singer', attrs: { singers: [LEA] } }] },
            { type: 'text', text: 'b', marks: [{ type: 'singer', attrs: { singers: [TOM] } }] }
          ]
        }
      ]
    }

    assert.equal(
      serialize(fromDoc(doc)),
      `<span singer="@[${LEA}]">a</span><span singer="@[${TOM}]">b</span>`
    )
  })
})
