import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { formatAbsenceRange, groupAbsencesByMonth } from './absenceRange.js'

/**
 * The absence labels and month grouping. Dates are bare `yyyy-MM-dd`, the shape the API sends, so
 * the assertions hold in whatever timezone the runner uses.
 *
 * Run with `npm test`.
 */

/** An absence as the API returns it. */
function absence(fields) {
  return { id: 'a', start_date: '2026-08-10', end_date: '2026-08-12', reason: null, ...fields }
}

describe('formatAbsenceRange', () => {
  it('prints a single day once', () => {
    assert.equal(formatAbsenceRange('2026-08-22', '2026-08-22'), '22 août')
  })

  it('names the month once when both ends share it', () => {
    assert.equal(formatAbsenceRange('2026-08-10', '2026-08-12'), '10 → 12 août')
  })

  it('names both months when the range crosses one', () => {
    assert.equal(formatAbsenceRange('2026-08-28', '2026-09-02'), '28 août → 2 septembre')
  })

  it('adds the year only when the range crosses one', () => {
    assert.equal(
      formatAbsenceRange('2026-12-28', '2027-01-03'),
      '28 décembre 2026 → 3 janvier 2027'
    )
  })

  // The same day in two different years is a range, not a single day: the day-of-month shortcut
  // must not swallow it.
  it('does not read the same day of two years as a single day', () => {
    assert.equal(formatAbsenceRange('2026-08-22', '2027-08-22'), '22 août 2026 → 22 août 2027')
  })

  it('returns an empty string for anything unusable', () => {
    assert.equal(formatAbsenceRange(null, '2026-08-12'), '')
    assert.equal(formatAbsenceRange('2026-08-10', ''), '')
    assert.equal(formatAbsenceRange('pas-une-date', '2026-08-12'), '')
  })
})

describe('groupAbsencesByMonth', () => {
  it('groups by the starting month, in chronological order', () => {
    const groups = groupAbsencesByMonth([
      absence({ id: 'sept', start_date: '2026-09-05', end_date: '2026-09-08' }),
      absence({ id: 'aug', start_date: '2026-08-10', end_date: '2026-08-12' })
    ])

    assert.deepEqual(
      groups.map((group) => [group.key, group.label, group.absences.map((a) => a.id)]),
      [
        ['2026-08', 'Août 2026', ['aug']],
        ['2026-09', 'Septembre 2026', ['sept']]
      ]
    )
  })

  it('sorts within a month by start date, then by end date', () => {
    const groups = groupAbsencesByMonth([
      absence({ id: 'late', start_date: '2026-08-20', end_date: '2026-08-21' }),
      absence({ id: 'long', start_date: '2026-08-10', end_date: '2026-08-18' }),
      absence({ id: 'short', start_date: '2026-08-10', end_date: '2026-08-11' })
    ])

    assert.deepEqual(
      groups[0].absences.map((a) => a.id),
      ['short', 'long', 'late']
    )
  })

  // Grouped by the start alone: a fortnight straddling the 1st is one entry in the register, not
  // one under each month it touches.
  it('lists an absence spanning two months once, under the month it starts in', () => {
    const groups = groupAbsencesByMonth([
      absence({ id: 'straddling', start_date: '2026-08-28', end_date: '2026-09-04' })
    ])

    assert.deepEqual(
      groups.map((group) => [group.key, group.absences.map((a) => a.id)]),
      [['2026-08', ['straddling']]]
    )
  })

  it('skips rows with no usable start date rather than throwing', () => {
    const groups = groupAbsencesByMonth([
      absence({ id: 'broken', start_date: null }),
      absence({ id: 'fine' })
    ])

    assert.deepEqual(
      groups.map((group) => group.absences.map((a) => a.id)),
      [['fine']]
    )
  })

  it('reads an empty or missing list as no groups', () => {
    assert.deepEqual(groupAbsencesByMonth([]), [])
    assert.deepEqual(groupAbsencesByMonth(undefined), [])
  })
})
