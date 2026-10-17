import assert from 'node:assert/strict'
import { after, afterEach, before, describe, it } from 'node:test'
import { i18n } from '../../i18n/index.js'
import absoluteDate from './absolute-date.js'

/** The output names a calendar day, so the runner's timezone decides which one. Pin it. */
const original = process.env.TZ
before(() => {
  process.env.TZ = 'Europe/Paris'
})
after(() => {
  process.env.TZ = original
})

describe('absoluteDate', () => {
  it('gives the time alone for a moment earlier today', () => {
    const earlierToday = new Date()
    earlierToday.setHours(9, 5, 0, 0)

    assert.equal(absoluteDate(earlierToday.toISOString()), '09:05')
  })

  it('names the day for an older moment in the same year', () => {
    const thisYear = new Date().getFullYear()

    assert.equal(absoluteDate(`${thisYear}-01-08T22:13:00+01:00`), '8 janvier à 22:13')
  })

  it('carries the year once the moment is not in this one', () => {
    assert.equal(absoluteDate('2019-05-08T22:13:00+02:00'), '8 mai 2019 à 22:13')
  })

  describe('in English', () => {
    afterEach(() => {
      i18n.global.locale.value = 'fr'
    })

    it('names the day and the time with its own word', () => {
      i18n.global.locale.value = 'en'
      const thisYear = new Date().getFullYear()

      assert.equal(absoluteDate(`${thisYear}-01-08T22:13:00+01:00`), '8 January at 22:13')
      assert.equal(absoluteDate('2019-05-08T22:13:00+02:00'), '8 May 2019 at 22:13')
    })
  })

  /**
   * `parseISO` throws rather than returning an invalid date when handed something that is not a
   * string, so these would take the whole conversation down with them.
   */
  it('returns nothing rather than throwing on a value it cannot read', () => {
    assert.equal(absoluteDate(undefined), '')
    assert.equal(absoluteDate(null), '')
    assert.equal(absoluteDate(''), '')
    assert.equal(absoluteDate('not-a-date'), '')
  })
})
