import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { kindsToAdd, serviceName, serviceStage } from './myServices.js'

describe('serviceStage', () => {
  it('says a published service is online, even when it misses a part', () => {
    assert.equal(serviceStage({ status: 'published', missing_to_publish: ['offers'] }), 'published')
  })

  it('says a draft with everything its kind needs is ready', () => {
    assert.equal(serviceStage({ status: 'draft', missing_to_publish: [] }), 'ready')
  })

  it('says a draft missing a part is incomplete', () => {
    assert.equal(
      serviceStage({ status: 'draft', missing_to_publish: ['places', 'offers'] }),
      'incomplete'
    )
  })
})

describe('kindsToAdd', () => {
  it('leaves out the kinds already offered, keeping the order given', () => {
    assert.deepEqual(kindsToAdd(['teaching', 'sound', 'photo'], [{ kind: 'sound' }]), [
      'teaching',
      'photo'
    ])
  })

  it('lists every kind for a member with no service', () => {
    assert.deepEqual(kindsToAdd(['teaching'], []), ['teaching'])
  })

  it('lists nothing once every kind is offered', () => {
    assert.deepEqual(kindsToAdd(['teaching'], [{ kind: 'teaching' }]), [])
  })
})

describe('serviceName', () => {
  // Writes the key back, so the test reads which label is asked for.
  const t = (key) => key

  it('is its headline, else the name of its kind', () => {
    assert.equal(
      serviceName({ kind: 'sound_engineering', headline: 'Mixage et prise de son' }, t),
      'Mixage et prise de son'
    )
    assert.equal(
      serviceName({ kind: 'teaching', headline: null }, t),
      'profile.services.kinds.teaching.label'
    )
    assert.equal(
      serviceName({ kind: 'teaching', headline: '' }, t),
      'profile.services.kinds.teaching.label'
    )
  })
})
