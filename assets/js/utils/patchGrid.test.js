import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  channelLabel,
  duplicateChannels,
  microphoneSuggestionGroups,
  nextChannel,
  occupiedChannels,
  parseChannel,
  renumberedChannels,
  rowClashes,
  selectionAfterClick
} from './patchGrid.js'

/**
 * The patch list table (#1099). Run with `npm test`.
 */

const mono = (channel) => ({ channel, stereo: false })
const stereo = (channel) => ({ channel, stereo: true })

describe('occupiedChannels', () => {
  it('is the channel of a mono row', () => {
    assert.deepEqual(occupiedChannels(mono(4)), [4])
  })

  it('is the channel and the next for a stereo pair', () => {
    assert.deepEqual(occupiedChannels(stereo(8)), [8, 9])
  })

  it('is nothing while the channel is blank', () => {
    assert.deepEqual(occupiedChannels({ channel: null, stereo: true }), [])
  })
})

describe('duplicateChannels', () => {
  it('finds two rows on the same channel', () => {
    assert.deepEqual([...duplicateChannels([mono(1), mono(2), mono(1)])], [1])
  })

  it('finds a mono row on the second channel of a stereo pair', () => {
    const duplicates = duplicateChannels([stereo(8), mono(9)])

    assert.deepEqual([...duplicates], [9])
    assert.equal(rowClashes(stereo(8), duplicates), true)
    assert.equal(rowClashes(mono(9), duplicates), true)
  })

  it('accepts a stereo pair followed by the next free channel', () => {
    assert.equal(duplicateChannels([stereo(8), mono(10)]).size, 0)
  })
})

describe('nextChannel', () => {
  it('starts at 1 on an empty list', () => {
    assert.equal(nextChannel([]), 1)
  })

  it('follows the highest channel taken, a stereo pair counting both', () => {
    assert.equal(nextChannel([mono(3), stereo(8)]), 10)
  })

  it('ignores a blank channel', () => {
    assert.equal(nextChannel([mono(2), { channel: null }]), 3)
  })

  it('is null past the last channel rather than an invalid number', () => {
    assert.equal(nextChannel([mono(999)]), null)
    assert.equal(nextChannel([stereo(998)]), null)
  })
})

describe('renumberedChannels', () => {
  it('numbers from 1 in the order shown, a stereo row taking two', () => {
    assert.deepEqual(renumberedChannels([mono(9), stereo(3), mono(1)]), [1, 2, 4])
  })

  it('starts where it is told, for a renumbered selection', () => {
    assert.deepEqual(renumberedChannels([mono(1), mono(1)], 5), [5, 6])
  })
})

describe('channelLabel', () => {
  it('reads « 8-9 » for a stereo pair', () => {
    assert.equal(channelLabel(stereo(8)), '8-9')
  })

  it('reads the channel of a mono row, and nothing when blank', () => {
    assert.equal(channelLabel(mono(4)), '4')
    assert.equal(channelLabel({ channel: null }), '')
  })
})

describe('parseChannel', () => {
  it('keeps the digits typed', () => {
    assert.equal(parseChannel('12'), 12)
    assert.equal(parseChannel(' 1a2 '), 12)
  })

  it('is null for an emptied cell', () => {
    assert.equal(parseChannel(''), null)
    assert.equal(parseChannel('abc'), null)
  })
})

describe('selectionAfterClick', () => {
  const keys = ['a', 'b', 'c', 'd', 'e']

  it('toggles a row on a plain click and makes it the anchor', () => {
    const first = selectionAfterClick(keys, new Set(), null, 'b', false)
    assert.deepEqual([...first.selected], ['b'])
    assert.equal(first.anchorKey, 'b')

    const second = selectionAfterClick(keys, first.selected, 'b', 'b', false)
    assert.deepEqual([...second.selected], [])
  })

  it('selects the range from the anchor on Maj + clic, in either direction', () => {
    const down = selectionAfterClick(keys, new Set(['b']), 'b', 'd', true)
    assert.deepEqual([...down.selected].sort(), ['b', 'c', 'd'])

    const up = selectionAfterClick(keys, new Set(['d']), 'd', 'b', true)
    assert.deepEqual([...up.selected].sort(), ['b', 'c', 'd'])
  })

  it('adds the range to what is already selected and keeps the anchor', () => {
    const result = selectionAfterClick(keys, new Set(['a', 'c']), 'c', 'e', true)

    assert.deepEqual([...result.selected].sort(), ['a', 'c', 'd', 'e'])
    assert.equal(result.anchorKey, 'c')
  })

  it('falls back to a toggle on Maj + clic with no anchor', () => {
    const result = selectionAfterClick(keys, new Set(), null, 'c', true)

    assert.deepEqual([...result.selected], ['c'])
  })
})

describe('microphoneSuggestionGroups', () => {
  const suggestions = {
    used: [
      { name: 'SM58', usage_count: 4 },
      { name: 'Beta 91A', usage_count: 1 }
    ],
    catalogue: ['Beta 52A', 'Beta 58A', 'e906']
  }

  it('offers the catalogue then the band s own models, each narrowed to the text typed', () => {
    assert.deepEqual(microphoneSuggestionGroups('beta', suggestions), [
      {
        label: 'Suggestions',
        items: [
          { name: 'Beta 52A', usageCount: null },
          { name: 'Beta 58A', usageCount: null }
        ]
      },
      { label: 'Déjà utilisés dans vos riders', items: [{ name: 'Beta 91A', usageCount: 1 }] }
    ])
  })

  it('offers everything on an empty cell', () => {
    const groups = microphoneSuggestionGroups('', suggestions)

    assert.deepEqual(
      groups.map((group) => group.items.length),
      [3, 2]
    )
  })

  it('drops a group with nothing matching', () => {
    assert.deepEqual(
      microphoneSuggestionGroups('sm5', suggestions).map((group) => group.label),
      ['Déjà utilisés dans vos riders']
    )
  })
})
