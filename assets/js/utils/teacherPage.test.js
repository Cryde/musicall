import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  availabilityByDay,
  cheapestPricing,
  levelRange,
  orderedLevels,
  placesSummary,
  pricingByDuration
} from './teacherPage.js'

describe('cheapestPricing', () => {
  it('picks the lowest price, whatever the order', () => {
    const pricing = [
      { duration: '1h', price: 4000 },
      { duration: '30min', price: 2500 },
      { duration: '1h30', price: 5500 }
    ]
    assert.deepEqual(cheapestPricing(pricing), { duration: '30min', price: 2500 })
  })

  it('is null without prices', () => {
    assert.equal(cheapestPricing([]), null)
    assert.equal(cheapestPricing(undefined), null)
  })
})

describe('pricingByDuration', () => {
  it('runs from the shortest lesson to the longest', () => {
    const pricing = [
      { duration: '1h30', price: 5000 },
      { duration: '2h', price: 7000 },
      { duration: '30min', price: 2500 },
      { duration: '1h', price: 4000 }
    ]
    assert.deepEqual(
      pricingByDuration(pricing).map((item) => item.duration),
      ['30min', '1h', '1h30', '2h']
    )
  })

  it('keeps an unknown duration last and leaves the input alone', () => {
    const pricing = [
      { duration: '3h', price: 9000 },
      { duration: '30min', price: 2500 }
    ]
    assert.deepEqual(
      pricingByDuration(pricing).map((item) => item.duration),
      ['30min', '3h']
    )
    assert.equal(pricing[0].duration, '3h')
    assert.deepEqual(pricingByDuration(undefined), [])
  })
})

describe('orderedLevels', () => {
  it('puts the levels in teaching order and drops unknown ones', () => {
    assert.deepEqual(orderedLevels(['advanced', 'expert', 'beginner', 'intermediate']), [
      'beginner',
      'intermediate',
      'advanced'
    ])
    assert.deepEqual(orderedLevels(undefined), [])
  })
})

describe('levelRange', () => {
  it('orders the levels as they are taught', () => {
    assert.deepEqual(levelRange(['advanced', 'beginner']), ['beginner', 'advanced'])
  })

  it('gives the same level twice when there is one', () => {
    assert.deepEqual(levelRange(['intermediate']), ['intermediate', 'intermediate'])
  })

  it('is null without levels, and ignores unknown ones', () => {
    assert.equal(levelRange([]), null)
    assert.equal(levelRange(['expert']), null)
    assert.equal(levelRange(undefined), null)
  })
})

describe('placesSummary', () => {
  it('names the teacher city first and lists the other ways', () => {
    const places = [
      { type: 'student_place', city: 'Uccle', radius: 10 },
      { type: 'teacher_place', city: 'Ixelles' },
      { type: 'online' }
    ]
    assert.deepEqual(placesSummary(places), {
      city: 'Ixelles',
      atTeacher: true,
      atStudent: true,
      online: true
    })
  })

  it('falls back on any place with a city', () => {
    assert.deepEqual(placesSummary([{ type: 'student_place', city: 'Uccle' }]), {
      city: 'Uccle',
      atTeacher: false,
      atStudent: true,
      online: false
    })
  })

  it('has no city for online only lessons, and nothing for no place', () => {
    assert.equal(placesSummary([{ type: 'online' }]).city, null)
    assert.deepEqual(placesSummary(undefined), {
      city: null,
      atTeacher: false,
      atStudent: false,
      online: false
    })
  })
})

describe('availabilityByDay', () => {
  it('gives the seven days from Monday, each with its slots in time order', () => {
    const week = availabilityByDay([
      { day_of_week: 'wednesday', start_time: '18:00', end_time: '20:00' },
      { day_of_week: 'monday', start_time: '17:00', end_time: '21:00' },
      { day_of_week: 'wednesday', start_time: '14:00', end_time: '16:00' }
    ])
    assert.deepEqual(
      week.map((day) => day.day),
      ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
    )
    assert.deepEqual(week[0].slots, [{ start: '17:00', end: '21:00' }])
    assert.deepEqual(week[1].slots, [])
    assert.deepEqual(week[2].slots, [
      { start: '14:00', end: '16:00' },
      { start: '18:00', end: '20:00' }
    ])
  })
})
