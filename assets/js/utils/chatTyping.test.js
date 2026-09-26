import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  activeTypists,
  createTypingNotifier,
  typingSentence,
  typistNames,
  withoutTypist,
  withTypist
} from './chatTyping.js'

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

describe('typists', () => {
  it('shows a typist until their time runs out, with no stop signal needed', () => {
    const typists = withTypist({}, 'lea', 1000, 5000)

    assert.deepEqual(activeTypists(typists, 5999), ['lea'])
    assert.deepEqual(activeTypists(typists, 6000), [])
  })

  it('extends a typist who keeps typing, and drops one on demand', () => {
    let typists = withTypist({}, 'lea', 0, 5000)
    typists = withTypist(typists, 'tom', 1000, 5000)
    typists = withTypist(typists, 'lea', 4000, 5000)

    assert.deepEqual(activeTypists(typists, 5500), ['lea', 'tom'])
    assert.deepEqual(activeTypists(withoutTypist(typists, 'lea'), 5500), ['tom'])
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

describe('typistNames', () => {
  it('names typists from the roster, with a fallback', () => {
    const members = [{ user_id: 'lea', display_name: 'Léa' }]

    assert.deepEqual(typistNames(['lea', 'ghost'], members), ['Léa', 'Un membre'])
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
