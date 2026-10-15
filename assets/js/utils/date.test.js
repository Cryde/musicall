import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import { formatDateCompact, formatDateCompactWithYear, formatDateLong } from './date.js'

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

    it('adds the year when asked', () => {
      assert.equal(formatDateCompactWithYear('2026-08-10'), '10 août 2026')
      assert.equal(formatDateCompactWithYear('2026-12-01'), '1 déc. 2026')
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
