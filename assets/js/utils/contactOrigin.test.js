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

describe('a message from a service page (#1408)', () => {
  const service = (fields) => ({
    type: 'service',
    service_id: 's1',
    service_kind: 'sound_engineering',
    instruments: [],
    styles: [],
    location_name: null,
    quote: null,
    ...fields
  })

  it('names what a quote asks for', () => {
    assert.equal(contactOriginLabel(service({ quote: { need: 'mixing' } })), 'Devis : mixage')
  })

  it('names the kind a question is about', () => {
    assert.equal(contactOriginLabel(service()), 'Question : ingénieur du son')
  })

  // An app or web build older than a new kind or need still says what it is.
  it('still says something for a need or a kind it does not know', () => {
    assert.equal(contactOriginLabel(service({ quote: { need: 'drone' } })), 'Demande de devis')
    assert.equal(contactOriginLabel(service({ service_kind: 'photography' })), 'Question')
  })

  it('reads the same once the service is deleted', () => {
    assert.equal(
      contactOriginLabel(service({ service_id: null, quote: { need: 'live' } })),
      'Devis : live'
    )
  })

  it('sums up the answers of a quote, and nothing for a question', () => {
    assert.equal(
      contactOriginDetails(
        service({
          quote: {
            need: 'mixing',
            tracks: 5,
            link: 'https://x.test',
            deadline: '2026-12-15',
            budget: '100_to_300'
          }
        })
      ),
      '5 titres · pour le 15 déc. 2026 · 100 à 300 €'
    )
    assert.equal(contactOriginDetails(service({ quote: { need: 'mixing', tracks: 1 } })), '1 titre')
    assert.equal(contactOriginDetails(service({ quote: { need: 'mixing' } })), null)
    assert.equal(contactOriginDetails(service()), null)
  })

  it('shows the icon of the kind', () => {
    assert.equal(contactOriginIcon(service()), 'pi pi-sliders-v')
    assert.equal(contactOriginIcon(service({ service_kind: 'photography' })), 'pi pi-briefcase')
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
