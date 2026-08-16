import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import { format } from 'date-fns'
import {
  agendaCalendarEventDates,
  agendaItemDayKeys,
  toAgendaDate,
  withoutAllDayPin
} from './agendaDate.js'

/**
 * Reading an agenda datetime on the day it was written for.
 *
 * Every assertion here runs twice, once east of UTC and once west of it, because that is the only
 * way this bug shows up: an all day event pinned to midnight UTC reads back as the previous day
 * for a reader in Guadeloupe or Martinique and looks perfectly fine in Paris. Node applies a
 * `process.env.TZ` reassignment to every later Date, so a suite can move the runner's timezone
 * itself rather than relying on whoever launched it.
 *
 * Run with `npm test`, and with `TZ=America/Martinique npm test` to prove the runner's own
 * timezone is not what is holding the assertions up.
 */

/** Paris in summer, UTC+2: everything already worked here, which is why this took so long to find. */
const EAST_OF_UTC = 'Europe/Paris'
/** Martinique, UTC-4 all year: a French user for whom an instant reading lands a day early. */
const WEST_OF_UTC = 'America/Martinique'

/** An agenda item as the aggregator returns it. */
function item(fields) {
  return {
    id: 'agenda-1',
    source: 'manual',
    is_all_day: false,
    datetime: '2026-08-12T20:30:00+02:00',
    end_datetime: null,
    ...fields
  }
}

const ALL_DAY = item({ is_all_day: true, datetime: '2026-08-12T00:00:00+00:00' })
const TIMED = item({})
// The aggregator sends is_all_day false on a finance due date and pads its date only column to
// midnight UTC, so the flag alone is not enough to spot one.
const FINANCE_DUE = item({ source: 'finance', datetime: '2026-08-12T00:00:00+00:00' })

/**
 * Runs a block in a fixed timezone. Written as a describe wrapper rather than a helper taking a
 * timezone so a failure names the timezone it failed in.
 */
function describeInTimeZone(timeZone, defineTests) {
  describe(`in ${timeZone}`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      process.env.TZ = original
    })

    defineTests()
  })
}

for (const timeZone of [EAST_OF_UTC, WEST_OF_UTC]) {
  describeInTimeZone(timeZone, () => {
    describe('toAgendaDate', () => {
      it('reads an all day entry on the day it was written for', () => {
        const date = toAgendaDate(ALL_DAY.datetime, true)

        assert.equal(date.getFullYear(), 2026)
        assert.equal(date.getMonth(), 7)
        assert.equal(date.getDate(), 12)
      })

      it('keeps the instant of a timed entry', () => {
        assert.equal(toAgendaDate(TIMED.datetime, false).toISOString(), '2026-08-12T18:30:00.000Z')
      })

      it('leaves a Date alone whatever the entry is', () => {
        const noon = new Date(2026, 7, 12, 12, 0)

        assert.equal(toAgendaDate(noon, true), noon)
        assert.equal(toAgendaDate(noon, false), noon)
      })

      it('reads anything unusable as null instead of throwing', () => {
        assert.equal(toAgendaDate(null, true), null)
        assert.equal(toAgendaDate('', false), null)
        assert.equal(toAgendaDate('pas une date', false), null)
      })
    })

    describe('agendaItemDayKeys', () => {
      it('groups an all day entry under the day it was written for', () => {
        assert.deepEqual(agendaItemDayKeys(ALL_DAY), ['2026-08-12'])
      })

      it('groups a finance due date under its own day despite its is_all_day being false', () => {
        assert.deepEqual(agendaItemDayKeys(FINANCE_DUE), ['2026-08-12'])
      })

      it('groups a timed entry under the local day the reader lives it', () => {
        // 20:30+02:00 is 14:30 in Martinique and 20:30 in Paris, so the 12th either way. The
        // instant is what moves here, and it has to.
        assert.deepEqual(agendaItemDayKeys(TIMED), ['2026-08-12'])
      })

      it('gives a late timed entry to the local day it falls on', () => {
        const lateGig = item({ datetime: '2026-08-13T01:30:00+02:00' })

        assert.deepEqual(agendaItemDayKeys(lateGig), [
          timeZone === EAST_OF_UTC ? '2026-08-13' : '2026-08-12'
        ])
      })

      it('spreads a multi day all day entry over every day it covers, ends included', () => {
        const festival = item({
          is_all_day: true,
          datetime: '2026-08-12T00:00:00+00:00',
          end_datetime: '2026-08-14T00:00:00+00:00'
        })

        assert.deepEqual(agendaItemDayKeys(festival), ['2026-08-12', '2026-08-13', '2026-08-14'])
      })

      it('keeps a one day all day entry on its one day when its end names that same day', () => {
        // The drawer's end picker allows the start day itself, so this shape reaches the list.
        const openDay = item({
          is_all_day: true,
          datetime: '2026-08-12T00:00:00+00:00',
          end_datetime: '2026-08-12T00:00:00+00:00'
        })

        assert.deepEqual(agendaItemDayKeys(openDay), ['2026-08-12'])
      })

      it('keeps a timed entry on its start day even when it has an end', () => {
        const rehearsal = item({
          datetime: '2026-08-12T20:30:00+02:00',
          end_datetime: '2026-08-12T23:00:00+02:00'
        })

        assert.deepEqual(agendaItemDayKeys(rehearsal), ['2026-08-12'])
      })

      it('gives back no day at all rather than throwing on a missing datetime', () => {
        assert.deepEqual(agendaItemDayKeys(item({ datetime: '' })), [])
        assert.deepEqual(agendaItemDayKeys(null), [])
      })
    })

    describe('agendaCalendarEventDates', () => {
      // The one that decides which day cell FullCalendar draws the event in. Its timeZone is left
      // at the default 'local', and for that setting a start carrying an offset is rewritten as
      // local wall clock before the cell is picked, so a bare date is the only stable input.
      it('hands an all day entry a bare date FullCalendar cannot shift', () => {
        assert.deepEqual(agendaCalendarEventDates(ALL_DAY), {
          start: '2026-08-12',
          end: undefined
        })
      })

      it('hands a finance due date a bare date too', () => {
        assert.deepEqual(agendaCalendarEventDates(FINANCE_DUE), {
          start: '2026-08-12',
          end: undefined
        })
      })

      it('hands a timed entry its instant untouched, offset included', () => {
        assert.deepEqual(agendaCalendarEventDates(TIMED), {
          start: '2026-08-12T20:30:00+02:00',
          end: undefined
        })
      })

      it('hands a timed entry its own end untouched', () => {
        const rehearsal = item({ end_datetime: '2026-08-12T23:00:00+02:00' })

        assert.deepEqual(agendaCalendarEventDates(rehearsal), {
          start: '2026-08-12T20:30:00+02:00',
          end: '2026-08-12T23:00:00+02:00'
        })
      })

      it('bumps a one day all day end to the day after so the entry still fills its own cell', () => {
        const openDay = item({
          is_all_day: true,
          datetime: '2026-08-12T00:00:00+00:00',
          end_datetime: '2026-08-12T00:00:00+00:00'
        })

        assert.deepEqual(agendaCalendarEventDates(openDay), {
          start: '2026-08-12',
          end: '2026-08-13'
        })
      })

      it('bumps a multi day all day end to the day after, which FullCalendar reads as exclusive', () => {
        const festival = item({
          is_all_day: true,
          datetime: '2026-08-12T00:00:00+00:00',
          end_datetime: '2026-08-14T00:00:00+00:00'
        })

        assert.deepEqual(agendaCalendarEventDates(festival), {
          start: '2026-08-12',
          end: '2026-08-15'
        })
      })
    })

    // The entry drawer fills its date pickers with toAgendaDate and writes them back with local
    // getters, so opening an entry and saving it untouched has to give the same day back. Seeding
    // the pickers from the instant moved an all day entry one day earlier on every single save.
    describe('the entry drawer load and save round trip', () => {
      it('gives an all day entry back on the day it was loaded from', () => {
        const loaded = toAgendaDate(ALL_DAY.datetime, true)

        assert.equal(format(loaded, 'yyyy-MM-dd'), '2026-08-12')
      })

      it('gives a timed entry back at the local hour the reader lives it', () => {
        const loaded = toAgendaDate(TIMED.datetime, false)

        assert.equal(format(loaded, 'HH:mm'), timeZone === EAST_OF_UTC ? '20:30' : '14:30')
      })
    })

    describe('withoutAllDayPin', () => {
      it('gives a label the written date of an all day entry, which no offset can move', () => {
        assert.equal(withoutAllDayPin(ALL_DAY.datetime, true), '2026-08-12')
      })

      it('gives a label the untouched instant of a timed entry', () => {
        assert.equal(withoutAllDayPin(TIMED.datetime, false), '2026-08-12T20:30:00+02:00')
      })

      it('leaves anything that is not a datetime string alone', () => {
        assert.equal(withoutAllDayPin(null, true), null)
        assert.equal(withoutAllDayPin(undefined, true), undefined)
      })
    })

    // The list groups an item under a day and the calendar draws it in a cell, from the same item.
    // They only ever agreed because the aggregated sources happen to send a null end date, so the
    // agreement is pinned rather than left to that.
    describe('the list grouping and the calendar grid agreeing', () => {
      it('covers the same days when the source, not the flag, is what makes an item all day', () => {
        // buildTask and buildFinance hardcode a null end today. The day either of them carries a
        // real one while still sending is_all_day false, the two views have to keep agreeing.
        const financeSpan = item({
          source: 'finance',
          datetime: '2026-08-12T00:00:00+00:00',
          end_datetime: '2026-08-14T00:00:00+00:00'
        })
        const dayKeys = agendaItemDayKeys(financeSpan)

        assert.deepEqual(dayKeys, ['2026-08-12', '2026-08-13', '2026-08-14'])
        assert.deepEqual(agendaCalendarEventDates(financeSpan), {
          start: dayKeys[0],
          // The calendar's all day end is exclusive, so it names the day after the last one grouped.
          end: '2026-08-15'
        })
      })
    })
  })
}
