import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  bandRowOf,
  bandsForm,
  bandsPayload,
  currentBandsSummary,
  emptyBand,
  MAX_BANDS
} from './musicianBands.js'

describe('bandsForm', () => {
  it('turns the stored bands into editable rows, with empty text for what is missing', () => {
    assert.deepEqual(
      bandsForm({
        bands: [
          {
            name: 'Ardent',
            style_id: 'rock',
            style_name: 'Rock',
            role: 'Guitare',
            current: true,
            url: 'https://ardent.bandcamp.com'
          },
          { name: 'Les Anciens', current: false }
        ]
      }),
      [
        {
          name: 'Ardent',
          styleId: 'rock',
          role: 'Guitare',
          current: true,
          url: 'https://ardent.bandcamp.com'
        },
        { name: 'Les Anciens', styleId: null, role: '', current: false, url: '' }
      ]
    )
  })

  it('gives no row without a profile', () => {
    assert.deepEqual(bandsForm(null), [])
  })
})

describe('bandsPayload', () => {
  it('trims, sends an empty field as null and drops only the rows left fully empty', () => {
    assert.deepEqual(
      bandsPayload([
        {
          name: '  Ardent ',
          styleId: 'rock',
          role: ' Guitare ',
          current: true,
          url: ' https://ardent.bandcamp.com '
        },
        { ...emptyBand(), name: '   ' },
        { ...emptyBand(), name: 'Les Anciens', current: false },
        { ...emptyBand(), url: 'https://lueur.bandcamp.com' }
      ]),
      [
        {
          name: 'Ardent',
          style_id: 'rock',
          role: 'Guitare',
          current: true,
          url: 'https://ardent.bandcamp.com'
        },
        { name: 'Les Anciens', style_id: null, role: null, current: false, url: null },
        { name: '', style_id: null, role: null, current: true, url: 'https://lueur.bandcamp.com' }
      ]
    )
  })

  it('never sends more than the limit', () => {
    const rows = Array.from({ length: MAX_BANDS + 2 }, (_, index) => ({
      ...emptyBand(),
      name: `Groupe ${index}`
    }))
    assert.equal(bandsPayload(rows).length, MAX_BANDS)
  })
})

describe('currentBandsSummary', () => {
  it('names the first current band and counts the other current ones', () => {
    const ardent = { name: 'Ardent', current: true }
    assert.deepEqual(
      currentBandsSummary([
        { name: 'Les Anciens', current: false },
        ardent,
        { name: 'Lueur', current: true }
      ]),
      { first: ardent, othersCount: 1 }
    )
  })

  it('says nothing without a current band', () => {
    assert.equal(currentBandsSummary([{ name: 'Les Anciens', current: false }]), null)
    assert.equal(currentBandsSummary(undefined), null)
  })
})

describe('bandRowOf', () => {
  it('gives the row of a band violation, counted from 1', () => {
    assert.equal(bandRowOf('bands[0].name'), 1)
    assert.equal(bandRowOf('bands[3].url'), 4)
  })

  it('gives nothing for the list itself or another field', () => {
    assert.equal(bandRowOf('bands'), null)
    assert.equal(bandRowOf('instruments[0].instrument_id'), null)
    assert.equal(bandRowOf(undefined), null)
  })
})
