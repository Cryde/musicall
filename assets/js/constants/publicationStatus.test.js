import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { galleryStatusLabel, publicationStatusLabel } from './publicationStatus.js'

// The texts the API sent as `status_label` before #687, which the pages must still show.
describe('publicationStatusLabel', () => {
  it('names each publication status', () => {
    assert.deepEqual([0, 1, 2].map(publicationStatusLabel), [
      'Brouillon',
      'Publié',
      'En validation'
    ])
  })

  it('names an unknown status rather than showing nothing', () => {
    assert.equal(publicationStatusLabel(9), 'Inconnu')
  })
})

describe('galleryStatusLabel', () => {
  it('names each gallery status, whose numbers differ from a publication', () => {
    assert.deepEqual([0, 1, 2].map(galleryStatusLabel), ['En ligne', 'Brouillon', 'En validation'])
  })

  it('names an unknown status rather than showing nothing', () => {
    assert.equal(galleryStatusLabel(9), 'Inconnu')
  })
})
