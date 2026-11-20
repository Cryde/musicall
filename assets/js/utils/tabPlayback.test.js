import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { clampSpeed, SPEED, toggleIndex, trainerNextSpeed } from './tabPlayback.js'

describe('clampSpeed', () => {
  it('keeps a speed on the 5 % grid', () => {
    assert.equal(clampSpeed(0.75), 0.75)
    assert.equal(clampSpeed(0.73), 0.75)
    assert.equal(clampSpeed(0.7 + 0.05), 0.75)
  })

  it('stays between 50 and 100 %', () => {
    assert.equal(clampSpeed(0.2), SPEED.MIN)
    assert.equal(clampSpeed(1.3), SPEED.MAX)
  })
})

describe('trainerNextSpeed', () => {
  it('adds 5 % on each pass of a loop', () => {
    assert.equal(trainerNextSpeed(0.7, { trainer: true, looping: true }), 0.75)
    assert.equal(trainerNextSpeed(0.95, { trainer: true, looping: true }), 1)
  })

  it('changes nothing at full speed, with the trainer off, or with nothing looping', () => {
    assert.equal(trainerNextSpeed(1, { trainer: true, looping: true }), null)
    assert.equal(trainerNextSpeed(0.7, { trainer: false, looping: true }), null)
    assert.equal(trainerNextSpeed(0.7, { trainer: true, looping: false }), null)
  })
})

describe('toggleIndex', () => {
  it('adds an index that is not in the list, and takes out one that is', () => {
    assert.deepEqual(toggleIndex([0, 2], 1), [0, 2, 1])
    assert.deepEqual(toggleIndex([0, 2], 2), [0])
  })

  it('leaves the list it was given untouched', () => {
    const list = [3]
    toggleIndex(list, 3)

    assert.deepEqual(list, [3])
  })
})
