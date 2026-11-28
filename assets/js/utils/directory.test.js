import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { directoryCards } from './directory.js'

const kinds = {
  teaching: { search: { testerOnly: false } },
  photography: { search: { testerOnly: true } },
  web: { search: { testerOnly: true } }
}
const summary = (cards) => cards.map(({ key, count }) => [key, count])

describe('directoryCards', () => {
  it('gives each kind its count, in the kinds order whatever the API order', () => {
    const counts = [
      { kind: 'photography', count: 4 },
      { kind: 'teaching', count: 12 },
      { kind: 'web', count: 0 }
    ]

    assert.deepEqual(summary(directoryCards(kinds, counts, true)), [
      ['teaching', 12],
      ['photography', 4],
      ['web', 0]
    ])
  })

  it('counts a kind the API left out as nobody yet', () => {
    assert.deepEqual(summary(directoryCards(kinds, [{ kind: 'teaching', count: 3 }], true)), [
      ['teaching', 3],
      ['photography', 0],
      ['web', 0]
    ])
  })

  it('never advertises a kind not yet announced to anyone but a tester', () => {
    const counts = [
      { kind: 'teaching', count: 3 },
      { kind: 'photography', count: 4 }
    ]

    assert.deepEqual(summary(directoryCards(kinds, counts, false)), [['teaching', 3]])
  })

  it('hands the kind config to the card', () => {
    assert.equal(directoryCards(kinds, [], true)[1].kind, kinds.photography)
  })
})
