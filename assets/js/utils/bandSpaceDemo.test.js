import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  canAddMore,
  ctaLabel,
  DEMO_ADD_LIMIT,
  dashboardModules,
  demoAgenda,
  demoBalances,
  demoExpenses,
  demoFiles,
  demoNotes,
  demoRoster,
  demoSetlist,
  demoTasks,
  describeBalance,
  formatEuros,
  formatSongDuration,
  INITIAL_SETLIST_ORDER,
  memberAt,
  moveSong,
  setlistMinutes,
  sizeLabel
} from './bandSpaceDemo.js'

/**
 * The demo on /band-space recomputes everything from the band size and a few clicks. What matters is
 * that the numbers a visitor reads stay coherent when they change the size mid-way: every row points
 * at someone still in the band, and the finance split still adds up.
 *
 * Run with `npm test`.
 */

describe('demoRoster and memberAt', () => {
  it('starts with the visitor', () => {
    assert.deepEqual(demoRoster(3), ['Vous', 'Léa', 'Tom'])
  })

  it('clamps an index past the end of a small band', () => {
    assert.equal(memberAt(2, 2), 'Léa')
  })

  it('counts a negative index from the end', () => {
    assert.equal(memberAt(4, -1), 'Sam')
    assert.equal(memberAt(2, -1), 'Léa')
  })
})

describe('sizeLabel', () => {
  it('marks the largest size as open ended', () => {
    assert.equal(sizeLabel(6), '6+')
    assert.equal(sizeLabel(4), '4')
  })
})

describe('ctaLabel', () => {
  it('names the band once one is typed', () => {
    assert.equal(ctaLabel('  Velvet Static '), 'Créer « Velvet Static »')
  })

  it('falls back to a generic label for a blank name', () => {
    assert.equal(ctaLabel('   '), 'Créer mon Band Space')
  })
})

describe('canAddMore', () => {
  it('stops at the demo limit', () => {
    assert.equal(canAddMore(DEMO_ADD_LIMIT - 1), true)
    assert.equal(canAddMore(DEMO_ADD_LIMIT), false)
  })
})

describe('dashboardModules', () => {
  it('puts the picked modules first, in module order', () => {
    assert.deepEqual(dashboardModules(['files', 'agenda']), [
      'agenda',
      'files',
      'taches',
      'setlists'
    ])
  })

  it('fills four slots with nothing picked', () => {
    assert.deepEqual(dashboardModules([]), ['agenda', 'taches', 'setlists', 'finances'])
  })
})

describe('demoAgenda', () => {
  it('slots an added date in chronological order', () => {
    const ids = demoAgenda(4, 1).map((event) => event.id)

    assert.deepEqual(ids, ['rehearsal-1', 'extra-rehearsal', 'gig', 'rehearsal-2'])
  })

  it('never adds more dates than exist', () => {
    assert.equal(demoAgenda(4, 5).length, 5)
  })

  it('has one member missing a rehearsal and everybody at the gig', () => {
    const [rehearsal, gig] = demoAgenda(4, 0)

    assert.equal(rehearsal.available, 3)
    assert.equal(gig.available, 4)
  })

  it('pads the day of the month', () => {
    assert.equal(demoAgenda(4, 0)[0].date, '01')
  })
})

describe('demoTasks', () => {
  it('marks the done ones and keeps assignees inside a duo', () => {
    const tasks = demoTasks(2, ['van'])

    assert.equal(tasks.find((task) => task.id === 'van').done, true)
    assert.equal(tasks.find((task) => task.id === 'rider').done, false)
    assert.ok(tasks.every((task) => ['Vous', 'Léa'].includes(task.who)))
  })
})

describe('moveSong', () => {
  it('swaps a song with its neighbour', () => {
    assert.deepEqual(moveSong(['a', 'b', 'c'], 1, -1), ['b', 'a', 'c'])
    assert.deepEqual(moveSong(['a', 'b', 'c'], 1, 1), ['a', 'c', 'b'])
  })

  it('leaves the order alone off either end', () => {
    assert.deepEqual(moveSong(['a', 'b'], 0, -1), ['a', 'b'])
    assert.deepEqual(moveSong(['a', 'b'], 1, 1), ['a', 'b'])
  })

  it('does not mutate the order it is given', () => {
    const order = ['a', 'b']
    moveSong(order, 0, 1)

    assert.deepEqual(order, ['a', 'b'])
  })
})

describe('setlist durations', () => {
  it('formats a song as minutes and padded seconds', () => {
    assert.equal(formatSongDuration(214), '3:34')
    assert.equal(formatSongDuration(305), '5:05')
  })

  it('totals the set in whole minutes whatever the order', () => {
    const reordered = moveSong(INITIAL_SETLIST_ORDER, 0, 1)

    assert.equal(setlistMinutes(demoSetlist(INITIAL_SETLIST_ORDER)), 24)
    assert.equal(setlistMinutes(demoSetlist(reordered)), 24)
  })
})

describe('demoNotes', () => {
  it('signs the untouched notes with their author', () => {
    const [rehearsal, covers] = demoNotes(4, {})

    assert.equal(rehearsal.who, 'Vous')
    assert.equal(covers.who, 'Sam')
    assert.equal(covers.isEdited, false)
  })

  it('shows the edited body, signed by the visitor', () => {
    const covers = demoNotes(4, { covers: 'On vote samedi.' })[1]

    assert.equal(covers.body, 'On vote samedi.')
    assert.equal(covers.who, 'Vous')
    assert.equal(covers.isEdited, true)
  })

  it('keeps an edit that emptied the note', () => {
    assert.equal(demoNotes(4, { covers: '' })[1].body, '')
  })
})

describe('demoFiles', () => {
  it('drops the deleted files', () => {
    const ids = demoFiles(4, ['demo', 'press']).map((file) => file.id)

    assert.deepEqual(ids, ['rider', 'bass'])
  })
})

describe('demoBalances', () => {
  it('splits the van evenly and credits whoever paid it', () => {
    const balances = demoBalances(4, demoExpenses(4, 0))

    assert.deepEqual(balances, [
      { name: 'Vous', cents: -4500 },
      { name: 'Léa', cents: 13500 },
      { name: 'Tom', cents: -4500 },
      { name: 'Sam', cents: -4500 }
    ])
  })

  it('always sums to zero once an expense is added', () => {
    for (const size of [2, 3, 4, 5, 6]) {
      const total = demoBalances(size, demoExpenses(size, 2)).reduce(
        (sum, row) => sum + row.cents,
        0
      )

      assert.ok(Math.abs(total) <= size, `size ${size} drifts by ${total} cents`)
    }
  })

  it('keeps a payer who left a smaller band inside it', () => {
    const expenses = demoExpenses(2, 2)

    assert.ok(expenses.every((expense) => ['Vous', 'Léa'].includes(expense.payer)))
  })
})

describe('formatEuros and describeBalance', () => {
  it('drops the decimals of a whole amount and uses a comma otherwise', () => {
    assert.equal(formatEuros(180), '180 €')
    assert.equal(formatEuros(11.25), '11,25 €')
  })

  it('says who pays and who is paid', () => {
    assert.equal(describeBalance(13500), 'reçoit 135 €')
    assert.equal(describeBalance(-1125), 'doit 11,25 €')
    assert.equal(describeBalance(0), "à l'équilibre")
  })
})
