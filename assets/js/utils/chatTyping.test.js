import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  createTypingNotifier,
  createTypistTracker,
  memberNames,
  typingSentence
} from './chatTyping.js'

/** A clock that only moves when the test says so. */
function fakeClock() {
  let now = 0
  let nextId = 1
  const scheduled = new Map()

  return {
    set(fn, delay) {
      const id = nextId++
      scheduled.set(id, { fn, at: now + delay })
      return id
    },
    clear(id) {
      scheduled.delete(id)
    },
    advance(ms) {
      now += ms
      for (const [id, { fn, at }] of [...scheduled]) {
        if (at <= now) {
          scheduled.delete(id)
          fn()
        }
      }
    },
    get pending() {
      return scheduled.size
    }
  }
}

function tracker(timers) {
  const heard = []
  const typists = createTypistTracker({
    onChange: (userIds) => heard.push(userIds),
    ttlMs: 5000,
    timers
  })

  return { typists, heard, current: () => heard.at(-1) ?? [] }
}

describe('createTypingNotifier', () => {
  it('sends once per interval however often it is told', () => {
    let clock = 0
    let sent = 0
    const notifier = createTypingNotifier({
      send: () => sent++,
      intervalMs: 3000,
      now: () => clock
    })

    notifier.notify()
    clock = 1000
    notifier.notify()
    clock = 2999
    notifier.notify()
    assert.equal(sent, 1)

    clock = 3000
    notifier.notify()
    assert.equal(sent, 2)
  })

  it('announces the next message at once after a reset', () => {
    let sent = 0
    const notifier = createTypingNotifier({ send: () => sent++, now: () => 10 })

    notifier.notify()
    notifier.reset()
    notifier.notify()
    assert.equal(sent, 2)
  })
})

describe('createTypistTracker', () => {
  it('shows a typist until their time runs out, with no stop signal needed', () => {
    const clock = fakeClock()
    const { typists, current } = tracker(clock)

    typists.seen('lea')
    clock.advance(4999)
    assert.deepEqual(current(), ['lea'])

    clock.advance(1)
    assert.deepEqual(current(), [])
    assert.equal(clock.pending, 0)
  })

  it('extends a typist who keeps typing, keeping the order they started in', () => {
    const clock = fakeClock()
    const { typists, heard, current } = tracker(clock)

    typists.seen('lea')
    clock.advance(1000)
    typists.seen('tom')
    clock.advance(3000)
    typists.seen('lea')
    clock.advance(1500)

    assert.deepEqual(current(), ['lea', 'tom'])
    // A typist seen again is no change to announce.
    assert.equal(heard.length, 2)

    // Tom's last signal was at 1000, Léa's at 4000.
    clock.advance(1000)
    assert.deepEqual(current(), ['lea'])
  })

  it('drops a typist whose message landed, and nobody it never saw', () => {
    const clock = fakeClock()
    const { typists, heard, current } = tracker(clock)

    typists.seen('lea')
    typists.seen('tom')
    typists.forget('lea')
    typists.forget('ghost')

    assert.deepEqual(current(), ['tom'])
    assert.equal(heard.length, 3)
    assert.equal(clock.pending, 1)
  })

  it('clears everybody at once, and says nothing when there was nobody', () => {
    const clock = fakeClock()
    const { typists, heard, current } = tracker(clock)

    typists.clear()
    assert.equal(heard.length, 0)

    typists.seen('lea')
    typists.seen('tom')
    typists.clear()

    assert.deepEqual(current(), [])
    assert.equal(clock.pending, 0)
  })
})

describe('createTypingNotifier, a send that did not go out', () => {
  it('does not start the interval', () => {
    let sent = 0
    let ready = false
    const notifier = createTypingNotifier({
      send: () => (ready ? ++sent : false),
      now: () => 0
    })

    notifier.notify()
    ready = true
    notifier.notify()
    assert.equal(sent, 1)
  })
})

describe('memberNames', () => {
  it('names typists from the roster, with a fallback', () => {
    const members = [{ user_id: 'lea', display_name: 'Léa' }]

    assert.deepEqual(memberNames(['lea', 'ghost'], members), ['Léa', 'Un membre'])
  })
})

describe('typingSentence', () => {
  it('names up to two, then counts', () => {
    assert.equal(typingSentence([]), '')
    assert.equal(typingSentence(['Léa']), 'Léa écrit…')
    assert.equal(typingSentence(['Léa', 'Tom']), 'Léa et Tom écrivent…')
    assert.equal(typingSentence(['Léa', 'Tom', 'Sam']), '3 personnes écrivent…')
  })
})
