import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { deliveryLine, kindSpecialities } from './visualServicePage.js'

describe('the specialities of a kind', () => {
  it('mirrors each kind in the order its form offers them', () => {
    assert.deepEqual(kindSpecialities('photography'), [
      'concert',
      'promo',
      'cover',
      'studio',
      'backstage_tour'
    ])
    assert.deepEqual(kindSpecialities('video'), [
      'clip',
      'live_session',
      'concert_film',
      'teaser',
      'editing'
    ])
    assert.deepEqual(kindSpecialities('graphic_design'), [
      'cover',
      'logo',
      'poster',
      'merch',
      'visual_identity',
      'social_media'
    ])
    assert.deepEqual(kindSpecialities('web'), [
      'band_site',
      'epk',
      'online_store',
      'redesign_maintenance'
    ])
    assert.deepEqual(kindSpecialities('teaching'), [])
  })
})

describe('deliveryLine', () => {
  // Shows which message was asked for, with what, and the count that picks its form.
  const t = (key, params, count) => `${key} ${JSON.stringify(params)} ${count}`

  it('says what is delivered and when', () => {
    assert.equal(
      deliveryLine({ deliverable: ' 40 photos retouchées ', delivery_days: 7 }, t),
      'profile.visual_service_page.delivery_within {"what":"40 photos retouchées","count":7} 7'
    )
  })

  it('says either half alone', () => {
    assert.equal(
      deliveryLine({ deliverable: null, delivery_days: 1 }, t),
      'profile.visual_service_page.delivery_days_only {"count":1} 1'
    )
    assert.equal(
      deliveryLine({ deliverable: 'Un clip monté', delivery_days: null }, t),
      'Un clip monté'
    )
  })

  it('says nothing when neither is given', () => {
    assert.equal(deliveryLine({ deliverable: '  ', delivery_days: null }, t), null)
  })
})
