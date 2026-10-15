import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import relativeDate from './relative-date.js'

/** The output names a calendar day and an hour, so the runner's timezone decides them. Pin it. */
const original = process.env.TZ
before(() => {
  process.env.TZ = 'Europe/Paris'
})
after(() => {
  if (original === undefined) {
    delete process.env.TZ
  } else {
    process.env.TZ = original
  }
})

describe('relativeDate', () => {
  it('says how long ago a recent moment was', () => {
    const threeHoursAgo = new Date(Date.now() - 3 * 60 * 60 * 1000)

    assert.equal(relativeDate(threeHoursAgo.toISOString()), 'il y a environ 3 heures')
  })

  it('gives the date of an older moment, with or without the hour', () => {
    assert.equal(relativeDate('2019-05-08T22:13:00+02:00'), '08 mai 2019, 22:13')
    assert.equal(relativeDate('2019-05-08T22:13:00+02:00', { showHours: false }), '08 mai 2019')
  })
})
