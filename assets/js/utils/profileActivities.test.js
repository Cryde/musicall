import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { musicianActivity, teacherActivity } from './profileActivities.js'

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
    pricing: [
      { duration: '1h', price: 4500 },
      { duration: '30min', price: 2500 }
    ],
    ...fields
  })

  it('names what they teach and starts from the cheapest lesson', () => {
    assert.deepEqual(teacherActivity(teacher({})), {
      instrumentNames: ['Guitare', 'Basse'],
      fromPrice: 2500,
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

  it('has no price without one, and no line without a teacher profile', () => {
    assert.equal(teacherActivity(teacher({ pricing: [] })).fromPrice, null)
    assert.equal(teacherActivity(null), null)
  })
})
