import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { i18n } from '../i18n/index.js'
import { contactOriginDetails, contactOriginIcon, contactOriginLabel } from './contactOrigin.js'

/**
 * What a direct message was sent from (#998). Run with `npm test`.
 */

function announce(fields = {}) {
  return {
    type: 'musician_announce',
    musician_announce_id: 'a1',
    teacher_profile_id: null,
    announce_type: 1,
    instruments: ['Batteur'],
    styles: ['Rock', 'Blues'],
    location_name: 'Lyon',
    ...fields
  }
}

describe('contactOriginLabel', () => {
  it('names what a band looks for', () => {
    assert.equal(contactOriginLabel(announce()), 'Annonce : recherche un batteur')
  })

  it('names a musician looking for a band', () => {
    assert.equal(
      contactOriginLabel(announce({ announce_type: 2, instruments: ['Bassiste'] })),
      'Annonce : Bassiste cherche un groupe'
    )
  })

  // The snapshot outlives the announce: a null id must not change the line.
  it('reads the same once the announce is deleted', () => {
    assert.equal(
      contactOriginLabel(announce({ musician_announce_id: null })),
      'Annonce : recherche un batteur'
    )
  })

  it('lists what a teacher teaches', () => {
    assert.equal(
      contactOriginLabel({
        type: 'teacher_profile',
        instruments: ['Piano', 'Guitare'],
        styles: []
      }),
      'Cours : Piano, Guitare'
    )
  })

  it('still says something for a teacher with no instrument', () => {
    assert.equal(
      contactOriginLabel({ type: 'teacher_profile', instruments: [], styles: [] }),
      'Cours particuliers'
    )
  })

  it('says nothing without an origin', () => {
    assert.equal(contactOriginLabel(null), null)
  })

  describe('a lesson request (#1322)', () => {
    const request = (fields) => ({ type: 'teacher_profile', styles: [], ...fields })

    it('names the instrument asked for', () => {
      assert.equal(
        contactOriginLabel(request({ lesson_request: 'trial', instruments: ['Basse'] })),
        "Demande de cours d'essai : basse"
      )
      assert.equal(
        contactOriginLabel(request({ lesson_request: 'first', instruments: ['Basse'] })),
        'Demande de premier cours : basse'
      )
    })

    it('lists what the teacher teaches when no instrument was chosen', () => {
      assert.equal(
        contactOriginLabel(
          request({ lesson_request: 'trial', instruments: ['Basse', 'Didjeridoo'] })
        ),
        "Demande de cours d'essai : basse, didjeridoo"
      )
    })

    it('still says what is asked without an instrument', () => {
      assert.equal(
        contactOriginLabel(request({ lesson_request: 'trial', instruments: [] })),
        "Demande de cours d'essai"
      )
      assert.equal(
        contactOriginLabel(request({ lesson_request: 'first', instruments: [] })),
        'Demande de premier cours'
      )
    })

    it('leaves a question from the teacher profile as it was', () => {
      assert.equal(
        contactOriginLabel(request({ lesson_request: null, instruments: ['Piano'] })),
        'Cours : Piano'
      )
    })
  })
})

describe('contactOriginDetails', () => {
  it('joins the styles and the place', () => {
    assert.equal(contactOriginDetails(announce()), 'Rock, Blues · Lyon')
  })

  describe('in English', () => {
    afterEach(() => {
      i18n.global.locale.value = 'fr'
    })

    it('joins them with a hyphen', () => {
      i18n.global.locale.value = 'en'

      assert.equal(contactOriginDetails(announce()), 'Rock, Blues - Lyon')
    })
  })

  it('leaves out what is missing', () => {
    assert.equal(contactOriginDetails(announce({ styles: [] })), 'Lyon')
    assert.equal(
      contactOriginDetails({ type: 'teacher_profile', instruments: ['Piano'], styles: [] }),
      null
    )
  })
})

describe('contactOriginIcon', () => {
  it('tells an announce from a course', () => {
    assert.equal(contactOriginIcon(announce()), 'pi pi-megaphone')
    assert.equal(contactOriginIcon({ type: 'teacher_profile' }), 'pi pi-book')
  })
})
