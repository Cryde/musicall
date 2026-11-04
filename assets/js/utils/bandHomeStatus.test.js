import assert from 'node:assert/strict'
import { after, afterEach, before, describe, it } from 'node:test'
import en from '../i18n/en/index.js'
import { i18n } from '../i18n/index.js'
import {
  agendaStatus,
  bandSubtitle,
  fileStatus,
  financeStatus,
  lastPastDate,
  memberStatus,
  noteStatus,
  relativeAgeLabel,
  setlistStatus,
  TONE,
  taskStatus,
  techRiderStatus,
  upcomingDates,
  whenLabel
} from './bandHomeStatus.js'

/**
 * The phone band home's tiles, worded as the Android app words them: these are the app's own cases
 * (band_home_status_test.dart in Cryde/musicall-app). Run with `npm test`.
 */

const ME = 'user-me'
const MINUTE = 60 * 1000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

// West of UTC is where a day pinned to midnight UTC would show the day before.
const TIME_ZONES = ['Europe/Paris', 'America/Martinique']

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

function timedDate(title, datetime) {
  return { source: 'manual', source_id: title, title, datetime, is_all_day: false, metadata: {} }
}

function allDayDate(title, day) {
  return {
    source: 'manual',
    source_id: title,
    title,
    datetime: `${day}T00:00:00+00:00`,
    is_all_day: true,
    metadata: {}
  }
}

function task(id, { assignees = [], status = 'todo' } = {}) {
  return { id, status, assignees: assignees.map((userId) => ({ id: userId })) }
}

for (const timeZone of TIME_ZONES) {
  describeInTimeZone(timeZone, () => {
    // Wednesday 4 November 2026, 14:00 UTC: the afternoon in both zones.
    const now = new Date('2026-11-04T14:00:00Z')

    describe('upcomingDates', () => {
      it('keeps only the dates somebody entered, not task deadlines', () => {
        const deadline = {
          source: 'task',
          title: 'Envoyer le rider',
          datetime: '2026-11-05T00:00:00+00:00',
          is_all_day: false
        }
        const gig = timedDate('Concert', '2026-11-06T19:30:00Z')

        assert.deepEqual(upcomingDates([deadline, gig], now), [gig])
      })

      it('drops a date that has started, soonest first for the rest', () => {
        const started = timedDate('Répète', '2026-11-04T13:00:00Z')
        const later = timedDate('Concert', '2026-11-20T19:30:00Z')
        const sooner = timedDate('Balances', '2026-11-07T16:00:00Z')

        assert.deepEqual(upcomingDates([started, later, sooner], now), [sooner, later])
      })

      it('keeps an all day date for the whole of its day, where the app drops it at midnight UTC', () => {
        const today = allDayDate('Enregistrement', '2026-11-04')
        const yesterday = allDayDate('Résidence', '2026-11-03')

        assert.deepEqual(upcomingDates([today, yesterday], now), [today])
      })
    })

    describe('lastPastDate', () => {
      it('is the latest date already behind the band', () => {
        const older = timedDate('Festival', '2026-10-15T18:00:00Z')
        const newer = timedDate('Répète', '2026-10-29T18:00:00Z')

        assert.equal(
          lastPastDate([newer, older, timedDate('Concert', '2026-11-20T19:30:00Z')], now),
          newer
        )
        assert.equal(lastPastDate([], now), null)
      })
    })

    describe('agendaStatus', () => {
      it('counts the dates to come and names the next one', () => {
        const concert = timedDate('Concert', '2026-11-07T16:00:00Z')
        const repete = timedDate('Répète', '2026-11-13T16:00:00Z')

        assert.deepEqual(agendaStatus([concert, repete]), {
          line1: '2 dates à venir',
          line2: '7 nov. · Concert',
          tone: TONE.NEUTRAL
        })
        assert.equal(agendaStatus([concert]).line1, '1 date à venir')
      })

      it('says nothing is planned, and when the last date was', () => {
        const festival = timedDate('Festival', '2026-10-15T16:00:00Z')

        assert.deepEqual(agendaStatus([], festival), {
          line1: 'Rien de prévu',
          line2: 'Dernière : 15 oct.',
          tone: TONE.NEUTRAL
        })
        assert.equal(agendaStatus([]).line2, null)
      })
    })

    describe('whenLabel', () => {
      it('reads today, tomorrow, then a number of days', () => {
        assert.equal(whenLabel(new Date('2026-11-04T20:00:00Z'), now), "Aujourd'hui")
        assert.equal(whenLabel(new Date('2026-11-05T16:00:00Z'), now), 'Demain')
        assert.equal(whenLabel(new Date('2026-11-07T16:00:00Z'), now), 'Dans 3 jours')
      })

      it('counts calendar days across a clock change', () => {
        assert.equal(
          whenLabel(new Date('2026-03-30T12:00:00Z'), new Date('2026-03-28T12:00:00Z')),
          'Dans 2 jours'
        )
      })
    })
  })
}

describe('taskStatus', () => {
  it('puts late tasks first, in red, then yours', () => {
    assert.deepEqual(taskStatus({ todo: 3, overdue: 1 }, [task('a', { assignees: [ME] })], ME), {
      line1: '1 en retard',
      line2: '1 pour toi',
      tone: TONE.DANGER
    })
  })

  it('names your tasks before the band total, and falls back on the counters without the list', () => {
    const tasks = [task('a', { assignees: [ME] }), task('b', { assignees: ['x'] })]

    assert.equal(taskStatus({ todo: 3 }, tasks, ME).line1, '1 pour toi')
    assert.equal(taskStatus({ todo: 3 }, null, ME).line1, '3 à faire')
  })

  it('puts « en cours » under « à faire »', () => {
    assert.deepEqual(taskStatus({ todo: 12, in_progress: 10 }), {
      line1: '12 à faire',
      line2: '10 en cours',
      tone: TONE.NEUTRAL
    })
  })

  it('counts the tasks nobody took', () => {
    const tasks = [task('a', { status: 'in_progress' })]

    assert.deepEqual(taskStatus({ in_progress: 1 }, tasks, ME), {
      line1: '1 en cours',
      line2: '1 sans responsable',
      tone: TONE.NEUTRAL
    })
  })

  it('prefers « en cours » to « sans responsable » under « à faire »', () => {
    const tasks = [task('a'), task('b', { status: 'in_progress' })]

    assert.equal(taskStatus({ todo: 2, in_progress: 1 }, tasks, ME).line2, '1 en cours')
  })

  it('says there is nothing to do when everything is done', () => {
    assert.deepEqual(taskStatus({ done: 4 }), {
      line1: 'Rien à faire',
      line2: null,
      tone: TONE.NEUTRAL
    })
  })

  it('leaves done tasks out of « sans responsable » and « pour toi »', () => {
    const unassigned = [task('a'), task('b'), task('c', { status: 'done' })]

    assert.equal(taskStatus({ todo: 2 }, unassigned, ME).line2, '2 sans responsable')
    assert.equal(
      taskStatus({ todo: 1 }, [task('a', { assignees: [ME], status: 'done' })], ME).line1,
      '1 à faire'
    )
  })

  it('has no second line without the list', () => {
    assert.deepEqual(taskStatus({ todo: 2 }), {
      line1: '2 à faire',
      line2: null,
      tone: TONE.NEUTRAL
    })
  })
})

describe('setlistStatus', () => {
  const ancienne = {
    name: 'Ancienne',
    creation_datetime: '2026-01-01T10:00:00+00:00',
    update_datetime: null,
    items: [{ type: 'song' }, { type: 'song' }, { type: 'song' }, { type: 'break' }]
  }
  const tournee = {
    name: 'Tournée',
    creation_datetime: '2026-09-01T10:00:00+00:00',
    update_datetime: null,
    items: [...Array.from({ length: 14 }, () => ({ type: 'song' })), { type: 'break' }]
  }

  it('counts the setlists and names the latest, songs only', () => {
    assert.deepEqual(setlistStatus([ancienne, tournee]), {
      line1: '2 setlists',
      line2: 'Tournée · 14 titres',
      tone: TONE.NEUTRAL
    })
  })

  it('takes the last edit over the creation date', () => {
    const edited = { ...ancienne, update_datetime: '2026-10-30T10:00:00+00:00' }

    assert.equal(setlistStatus([edited, tournee]).line2, 'Ancienne · 3 titres')
  })

  it('says when there is none', () => {
    assert.deepEqual(setlistStatus([]), {
      line1: 'Aucune setlist',
      line2: null,
      tone: TONE.NEUTRAL
    })
  })
})

describe('techRiderStatus', () => {
  const now = new Date('2026-11-04T14:00:00Z')

  it('names the rider edited last, and when', () => {
    const club = { name: 'Club', update_datetime: new Date(now - 9 * DAY).toISOString() }
    const festival = { name: 'Festival', update_datetime: new Date(now - 2 * HOUR).toISOString() }

    assert.deepEqual(techRiderStatus([club, festival], now), {
      line1: 'Festival',
      line2: 'Modifié il y a 2 h',
      tone: TONE.NEUTRAL
    })
  })

  it('takes the first one when none says when', () => {
    assert.deepEqual(techRiderStatus([{ name: 'Club' }, { name: 'Festival' }], now), {
      line1: 'Club',
      line2: null,
      tone: TONE.NEUTRAL
    })
    assert.equal(techRiderStatus([], now).line1, 'Aucun rider')
  })
})

describe('fileStatus', () => {
  it('counts the files and says how much of the space they take', () => {
    assert.deepEqual(fileStatus(42, { used_bytes: 1024, quota_bytes: 2048 }), {
      line1: '42 fichiers',
      line2: '1.0 Ko sur 2.0 Ko',
      tone: TONE.NEUTRAL
    })
    assert.equal(
      fileStatus(0, { used_bytes: 0, quota_bytes: 5 * 1024 ** 3 }).line2,
      '0 o sur 5.00 Go'
    )
  })

  it('still counts without the quota', () => {
    assert.deepEqual(fileStatus(1), { line1: '1 fichier', line2: null, tone: TONE.NEUTRAL })
    assert.equal(fileStatus(0).line1, 'Aucun fichier')
  })
})

describe('financeStatus', () => {
  it('shows where the band will stand this month', () => {
    assert.deepEqual(financeStatus({ total_income_all: 50000, total_expense_all: 20000 }), {
      line1: '300,00 €',
      line2: 'Solde prévu ce mois',
      tone: TONE.NEUTRAL
    })
  })

  it('turns red below zero', () => {
    assert.deepEqual(financeStatus({ total_income_all: 1000, total_expense_all: 13000 }), {
      line1: '-120,00 €',
      line2: 'Solde prévu ce mois',
      tone: TONE.DANGER
    })
    assert.equal(financeStatus({ total_income_all: 0, total_expense_all: 4500 }).line1, '-45,00 €')
  })

  it('says when nothing happens this month', () => {
    assert.deepEqual(financeStatus({ total_income_all: 0, total_expense_all: 0 }), {
      line1: 'Rien ce mois-ci',
      line2: null,
      tone: TONE.NEUTRAL
    })
  })
})

describe('noteStatus', () => {
  const now = new Date('2026-11-04T14:00:00Z')

  it('counts the notes and says when one last changed', () => {
    const notes = [
      { creation_datetime: new Date(now - 3 * DAY).toISOString(), update_datetime: null },
      {
        creation_datetime: new Date(now - 9 * DAY).toISOString(),
        update_datetime: new Date(now - 5 * MINUTE).toISOString()
      }
    ]

    assert.deepEqual(noteStatus(notes, now), {
      line1: '2 notes',
      line2: 'Modifiée il y a 5 min',
      tone: TONE.NEUTRAL
    })
  })

  it('has no second line without a date, and says when there is none', () => {
    assert.deepEqual(noteStatus([{}], now), { line1: '1 note', line2: null, tone: TONE.NEUTRAL })
    assert.equal(noteStatus([], now).line1, 'Aucune note')
  })
})

describe('memberStatus', () => {
  const members = (count) => Array.from({ length: count }, (_, index) => ({ id: `m${index}` }))

  it('counts the pending invitations for an admin', () => {
    assert.deepEqual(memberStatus(members(2), [{ status: 'pending' }, { status: 'accepted' }]), {
      line1: '2 membres',
      line2: '1 invitation en attente',
      tone: TONE.NEUTRAL
    })
    assert.equal(
      memberStatus(members(1), [{ status: 'pending' }, { status: 'pending' }]).line2,
      '2 invitations en attente'
    )
  })

  it('suggests inviting when an admin has none pending', () => {
    assert.equal(memberStatus(members(1), []).line2, "Inviter quelqu'un")
  })

  it('says nothing of invitations to a member, who cannot see them', () => {
    assert.deepEqual(memberStatus(members(1), null), {
      line1: '1 membre',
      line2: null,
      tone: TONE.NEUTRAL
    })
  })
})

describe('bandSubtitle', () => {
  it('joins the members and the other bands, leaving out what is zero', () => {
    assert.equal(bandSubtitle(6, 5), '6 membres · 5 autres groupes')
    assert.equal(bandSubtitle(1, 0), '1 membre')
    assert.equal(bandSubtitle(0, 1), '1 autre groupe')
    assert.equal(bandSubtitle(0, 0), null)
  })
})

describe('relativeAgeLabel', () => {
  const now = new Date('2026-11-04T14:00:00Z')

  it('reads elapsed time, short enough for a tile', () => {
    assert.equal(relativeAgeLabel(new Date(now - 30 * 1000), now), "à l'instant")
    assert.equal(relativeAgeLabel(new Date(now - 40 * MINUTE), now), 'il y a 40 min')
    assert.equal(relativeAgeLabel(new Date(now - 5 * HOUR), now), 'il y a 5 h')
    assert.equal(relativeAgeLabel(new Date(now - 26 * HOUR), now), 'hier')
    assert.equal(relativeAgeLabel(new Date(now - 3 * DAY), now), 'il y a 3 jours')
  })

  it('reads a date in the future as just now', () => {
    assert.equal(relativeAgeLabel(new Date(now.getTime() + 5 * MINUTE), now), "à l'instant")
  })
})

describe('in English', () => {
  // English is loaded on demand in the app (locale.js), so the test loads it itself.
  before(() => {
    i18n.global.setLocaleMessage('en', en)
  })

  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('words the tiles in English', () => {
    i18n.global.locale.value = 'en'

    assert.equal(
      taskStatus({ todo: 3, overdue: 1 }, [task('a', { assignees: [ME] })], ME).line2,
      '1 for you'
    )
    assert.deepEqual(fileStatus(42, { used_bytes: 1024, quota_bytes: 2048 }), {
      line1: '42 files',
      line2: '1.0 KB of 2.0 KB',
      tone: TONE.NEUTRAL
    })
    assert.equal(bandSubtitle(6, 1), '6 members - 1 other band')
    assert.equal(memberStatus([{}], []).line2, 'Invite someone')
  })
})
