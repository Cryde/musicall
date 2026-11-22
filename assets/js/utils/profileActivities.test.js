import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  musicianActivity,
  priceUnitLabel,
  serviceCards,
  servicePriceText,
  teacherActivity
} from './profileActivities.js'

describe('musicianActivity', () => {
  it('names the first instrument and says whether they are available', () => {
    assert.deepEqual(
      musicianActivity({
        availability_status: 'open_to_collaborations',
        availability_status_label: 'Ouvert aux collaborations',
        instruments: [{ instrument_name: 'Guitariste' }, { instrument_name: 'Bassiste' }]
      }),
      { instrumentName: 'Guitariste', statusLabel: 'Ouvert aux collaborations', isAvailable: true }
    )
  })

  it('is not available when they said so, and names nothing they did not fill in', () => {
    assert.deepEqual(
      musicianActivity({
        availability_status: 'not_available',
        availability_status_label: 'Pas disponible',
        instruments: []
      }),
      { instrumentName: null, statusLabel: 'Pas disponible', isAvailable: false }
    )
    assert.deepEqual(musicianActivity({ instruments: [] }), {
      instrumentName: null,
      statusLabel: null,
      isAvailable: false
    })
  })

  it('is no line without a musician profile', () => {
    assert.equal(musicianActivity(null), null)
  })
})

describe('teacherActivity', () => {
  const teacher = (fields) => ({
    instruments: [{ instrument_name: 'Guitare' }, { instrument_name: 'Basse' }],
    offers_trial: false,
    ...fields
  })

  it('names what they teach', () => {
    assert.deepEqual(teacherActivity(teacher({})), {
      instrumentNames: ['Guitare', 'Basse'],
      trial: null,
      trialPrice: null
    })
  })

  it('tells a free trial from a paid one', () => {
    assert.equal(teacherActivity(teacher({ offers_trial: true, trial_price: 0 })).trial, 'free')
    assert.equal(teacherActivity(teacher({ offers_trial: true, trial_price: null })).trial, 'free')
    const paid = teacherActivity(teacher({ offers_trial: true, trial_price: 1500 }))
    assert.equal(paid.trial, 'paid')
    assert.equal(paid.trialPrice, 1500)
  })

  it('adds nothing without a teacher profile', () => {
    assert.equal(teacherActivity(null), null)
  })
})

describe('serviceCards', () => {
  const services = [
    {
      id: 'draft',
      kind: 'teaching',
      status: 'draft',
      url: 'https://musicall.com/u/julien/teacher'
    },
    {
      id: 'mixing',
      kind: 'sound_engineering',
      status: 'published',
      headline: 'Mixage et prise de son',
      url: 'https://musicall.com/u/julien/sound-engineer',
      price: 8000,
      price_unit: 'track'
    }
  ]

  it('keeps the member order and marks a draft for its owner', () => {
    assert.deepEqual(serviceCards(services, true), [
      {
        id: 'draft',
        kind: 'teaching',
        headline: null,
        isDraft: true,
        price: null,
        priceUnit: null,
        priceQuantity: null
      },
      {
        id: 'mixing',
        kind: 'sound_engineering',
        headline: 'Mixage et prise de son',
        isDraft: false,
        price: 8000,
        priceUnit: 'track',
        priceQuantity: null
      }
    ])
  })

  it('leaves the drafts out when the owner looks as a visitor', () => {
    assert.deepEqual(
      serviceCards(services, false).map((card) => card.id),
      ['mixing']
    )
  })

  it('is no card without services', () => {
    assert.deepEqual(serviceCards(undefined, true), [])
  })
})

describe('priceUnitLabel', () => {
  it('says the length of a lesson, and what a sound engineer price buys', () => {
    assert.deepEqual(priceUnitLabel({ priceUnit: 'lesson', priceQuantity: 30 }), {
      key: 'profile.public.activities.units.lesson',
      params: { minutes: 30 }
    })
    assert.deepEqual(priceUnitLabel({ priceUnit: 'package', priceQuantity: 10 }), {
      key: 'profile.public.activities.units.package',
      params: {}
    })
    assert.deepEqual(priceUnitLabel({ priceUnit: 'track', priceQuantity: null }), {
      key: 'profile.sound_engineer.units.track',
      params: {}
    })
  })

  it("says what a photographer's or videographer's event price buys", () => {
    assert.deepEqual(priceUnitLabel({ priceUnit: 'event', priceQuantity: null }), {
      key: 'profile.visual_service.units.event',
      params: {}
    })
  })

  it('says nothing it cannot say', () => {
    assert.equal(priceUnitLabel({ priceUnit: 'lesson', priceQuantity: null }), null)
    assert.equal(priceUnitLabel({ priceUnit: null, priceQuantity: null }), null)
  })
})

describe('servicePriceText', () => {
  // Writes the key and its parameters back, so the test reads which message is asked for.
  const t = (key, params = {}) => `${key}${JSON.stringify(params)}`

  it('leads with the price, then what it buys', () => {
    assert.equal(
      servicePriceText({ price: 2500, priceUnit: 'lesson', priceQuantity: 30 }, t),
      'profile.public.activities.from_price{"price":"25€"} profile.public.activities.units.lesson{"minutes":30}'
    )
  })

  it('gives the price alone when what it buys cannot be said, and nothing without a price', () => {
    assert.equal(
      servicePriceText({ price: 2500, priceUnit: 'lesson', priceQuantity: null }, t),
      'profile.public.activities.from_price{"price":"25€"}'
    )
    assert.equal(servicePriceText({ price: null, priceUnit: null, priceQuantity: null }, t), null)
  })
})
