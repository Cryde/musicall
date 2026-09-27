import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  locationLabel,
  nextStep,
  pluralMusicianName,
  quickPicks,
  signupHeadline,
  soughtLabel,
  stepQuestion
} from './guidedSearch.js'

describe('nextStep', () => {
  it('asks the questions in order, skipping the ones already answered', () => {
    assert.equal(nextStep([]), 'instrument')
    assert.equal(nextStep(['instrument']), 'location')
    assert.equal(nextStep(['location']), 'instrument')
    assert.equal(nextStep(['instrument', 'location']), 'styles')
    assert.equal(nextStep(['instrument', 'location', 'styles']), null)
  })
})

describe('quickPicks', () => {
  it('keeps the slugs order and leaves out what the list lacks', () => {
    const items = [{ slug: 'guitare' }, { slug: 'batterie' }]
    assert.deepEqual(quickPicks(items, ['batterie', 'basse', 'guitare']), [
      { slug: 'batterie' },
      { slug: 'guitare' }
    ])
  })
})

describe('pluralMusicianName', () => {
  it('puts the first word of each form in the plural', () => {
    assert.equal(pluralMusicianName('Batteur / Batteuse'), 'batteurs / batteuses')
    assert.equal(pluralMusicianName('Guitariste'), 'guitaristes')
    assert.equal(pluralMusicianName('Joueur / Joueuse de Djembé'), 'joueurs / joueuses de djembé')
  })

  it('leaves a word already plural as it is', () => {
    assert.equal(pluralMusicianName('Choeurs'), 'choeurs')
  })
})

describe('texts', () => {
  const drums = { musician_name: 'Batteur / Batteuse' }
  const brussels = { name: 'Bruxelles' }
  const styles = [{ name: 'Rock' }, { name: 'Métal' }]

  it('words the questions for who is looking', () => {
    assert.equal(stepQuestion('location', false), 'Où répète votre groupe ?')
    assert.equal(stepQuestion('location', true), 'Où cherchez-vous un groupe ?')
  })

  it('names what is sought', () => {
    assert.equal(soughtLabel({ lookingForBand: false, instrument: drums }), 'batteurs / batteuses')
    assert.equal(soughtLabel({ lookingForBand: false, instrument: null }), 'musiciens')
    assert.equal(soughtLabel({ lookingForBand: true, instrument: drums }), 'groupes')
  })

  it('labels the place with its radius when there is one', () => {
    assert.equal(locationLabel(brussels, 25), 'Bruxelles · 25 km')
    assert.equal(locationLabel(brussels, null), 'Bruxelles')
    assert.equal(locationLabel(null, 25), null)
  })

  it('builds the registration headline from what the search has', () => {
    assert.equal(
      signupHeadline({ lookingForBand: false, instrument: drums, styles, location: brussels }),
      'Encore plus de batteurs / batteuses rock / métal autour de Bruxelles'
    )
    assert.equal(
      signupHeadline({ lookingForBand: true, instrument: drums, styles: [], location: null }),
      'Encore plus de groupes'
    )
  })
})
