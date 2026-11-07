import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  instrumentsPayload,
  musicianForm,
  musicianPayload,
  musicianViolationMessage
} from './musicianProfileForm.js'

const profile = {
  availability_status: 'open_to_collaborations',
  instruments: [{ instrument_id: 'g', skill_level: 'advanced' }],
  styles: [{ id: 'rock' }, { id: 'jazz' }],
  bands: [{ name: 'Ardent', style_id: null, role: 'Basse', current: true, url: null }]
}

describe('musicianForm', () => {
  it('holds the stored profile, and nothing without one', () => {
    const form = musicianForm(profile)
    assert.equal(form.availabilityStatus, 'open_to_collaborations')
    assert.deepEqual(form.instruments, [{ instrumentId: 'g', skillLevel: 'advanced' }])
    assert.deepEqual(form.styleIds, ['rock', 'jazz'])
    assert.equal(form.bands[0].name, 'Ardent')
    assert.deepEqual(musicianForm(null), {
      availabilityStatus: null,
      instruments: [],
      styleIds: [],
      bands: []
    })
  })
})

describe('instrumentsPayload', () => {
  it('drops incomplete rows and keeps the last level of an instrument chosen twice', () => {
    assert.deepEqual(
      instrumentsPayload([
        { instrumentId: 'g', skillLevel: 'beginner' },
        { instrumentId: null, skillLevel: 'advanced' },
        { instrumentId: 'b', skillLevel: null },
        { instrumentId: 'g', skillLevel: 'professional' }
      ]),
      [{ instrument_id: 'g', skill_level: 'professional' }]
    )
  })
})

describe('musicianPayload', () => {
  it('sends every block by default', () => {
    assert.deepEqual(Object.keys(musicianPayload(musicianForm(profile))), [
      'availability_status',
      'instruments',
      'style_ids',
      'bands'
    ])
  })

  it('sends only the block a pencil opened, so the others keep their value', () => {
    const form = musicianForm(profile)
    assert.deepEqual(musicianPayload(form, ['styles']), { style_ids: ['rock', 'jazz'] })
    assert.deepEqual(musicianPayload(form, ['availability']), {
      availability_status: 'open_to_collaborations'
    })
    assert.deepEqual(Object.keys(musicianPayload(form, ['bands'])), ['bands'])
  })
})

describe('musicianViolationMessage', () => {
  const t = (key, params) => `${key}:${params.number}:${params.message}`

  it('names the band row a violation is about', () => {
    assert.equal(
      musicianViolationMessage({ propertyPath: 'bands[1].name', message: 'Vide' }, t),
      'profile.musician.edit.band_violation:2:Vide'
    )
    assert.equal(musicianViolationMessage({ propertyPath: 'style_ids', message: 'Non' }, t), 'Non')
  })
})
