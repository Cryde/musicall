import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { lyricsReaderStep } from './lyricsReaderStep.js'

const songs = [{ title: 'Nuit blanche' }, { title: 'Les Ondes' }, { title: 'Ligne 13' }]

describe('lyricsReaderStep', () => {
  it('names the position and the song that comes next', () => {
    assert.deepEqual(lyricsReaderStep(songs, 0), {
      index: 0,
      position: '1 / 3',
      nextTitle: 'Les Ondes',
      hasPrevious: false,
      hasNext: true
    })
  })

  it('has nothing after the last song', () => {
    const step = lyricsReaderStep(songs, 2)
    assert.equal(step.nextTitle, null)
    assert.equal(step.hasNext, false)
    assert.equal(step.hasPrevious, true)
  })

  it('keeps an index past either end on the songs there are', () => {
    assert.equal(lyricsReaderStep(songs, 9).position, '3 / 3')
    assert.equal(lyricsReaderStep(songs, -1).position, '1 / 3')
  })

  it('reads a single song as one of one', () => {
    assert.deepEqual(lyricsReaderStep([{ title: 'Seule' }], 0), {
      index: 0,
      position: '1 / 1',
      nextTitle: null,
      hasPrevious: false,
      hasNext: false
    })
  })
})
