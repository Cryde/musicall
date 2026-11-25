import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { MAX_GEAR, parseGear } from './soundEngineerPage.js'

describe('parseGear', () => {
  it('splits on commas and lines, trims, and drops empties and repeats', () => {
    assert.deepEqual(parseGear(' Reaper, UAD Apollo x8\nNeumann U87,, Reaper \n'), [
      'Reaper',
      'UAD Apollo x8',
      'Neumann U87'
    ])
  })

  it('keeps at most the limit the API allows', () => {
    const text = Array.from({ length: MAX_GEAR + 5 }, (_, i) => `Item ${i}`).join(',')
    assert.equal(parseGear(text).length, MAX_GEAR)
  })
})
