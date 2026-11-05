import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import {
  formatDateCompact,
  formatDateCompactInYear,
  formatDateCompactWithYear,
  formatDateLong,
  formatDateRangeCompact,
  formatDateWithWeekday
} from './date.js'

// A bare day is read as local midnight, so the runner's timezone must not move it. West of UTC is
// where reading it as an instant would show the day before.
for (const timeZone of ['Europe/Paris', 'America/New_York']) {
  describe(`date formatting in ${timeZone}`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      if (original === undefined) {
        delete process.env.TZ
      } else {
        process.env.TZ = original
      }
    })

    it('writes the compact day and month in French', () => {
      assert.equal(formatDateCompact('2026-08-10'), '10 août')
      assert.equal(formatDateCompact('2026-02-03'), '3 févr.')
    })

    it('takes a Date as well as a string', () => {
      assert.equal(formatDateCompact(new Date(2026, 7, 10, 18, 30)), '10 août')
    })

    it('adds the year when asked', () => {
      assert.equal(formatDateCompactWithYear('2026-08-10'), '10 août 2026')
      assert.equal(formatDateCompactWithYear('2026-12-01'), '1 déc. 2026')
    })

    it('leaves this year out of a row date, and names any other', () => {
      const now = new Date(2026, 10, 4, 12, 0)
      assert.equal(formatDateCompactInYear('2026-11-04T08:56:00+01:00', now), '4 nov.')
      assert.equal(formatDateCompactInYear('2025-12-31', now), '31 déc. 2025')
      assert.equal(formatDateCompactInYear(null, now), '')
    })

    it('writes a period with its year once, or twice across a new year', () => {
      assert.equal(
        formatDateRangeCompact(new Date(2026, 10, 4), new Date(2026, 11, 4)),
        '4 nov. → 4 déc. 2026'
      )
      assert.equal(
        formatDateRangeCompact(new Date(2026, 11, 28), new Date(2027, 0, 4)),
        '28 déc. 2026 → 4 janv. 2027'
      )
      assert.equal(formatDateRangeCompact(null, new Date(2026, 0, 1)), '')
    })

    it('puts the weekday before the day', () => {
      assert.equal(formatDateWithWeekday('2026-11-04'), 'mer. 4 nov. 2026')
      assert.equal(formatDateWithWeekday(new Date(2026, 7, 10, 18, 30)), 'lun. 10 août 2026')
      assert.equal(formatDateWithWeekday(null), '')
    })

    it('spells the month out for a sentence', () => {
      assert.equal(formatDateLong('2026-08-10'), '10 août 2026')
      assert.equal(formatDateLong('2026-02-03'), '3 février 2026')
    })

    it('returns an empty string for no date', () => {
      assert.equal(formatDateCompact(''), '')
      assert.equal(formatDateCompactWithYear(null), '')
      assert.equal(formatDateLong(undefined), '')
    })
  })
}
