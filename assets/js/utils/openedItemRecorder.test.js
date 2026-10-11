import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'
import { createOpenedItemRecorder, REPEAT_WINDOW_MS } from './openedItemRecorder.js'

describe('createOpenedItemRecorder', () => {
  let sent
  let clock
  let record

  beforeEach(() => {
    sent = []
    clock = 1_000_000
    record = createOpenedItemRecorder({
      send: (...args) => sent.push(args),
      now: () => clock
    })
  })

  it('sends an open', () => {
    record('space-1', 'task', 'task-1')

    assert.deepEqual(sent, [['space-1', 'task', 'task-1']])
  })

  it('drops the same open reported again within the window', () => {
    record('space-1', 'task', 'task-1')
    clock += REPEAT_WINDOW_MS - 1
    record('space-1', 'task', 'task-1')

    assert.equal(sent.length, 1)
  })

  it('counts the same item opened again once the window has passed', () => {
    record('space-1', 'task', 'task-1')
    clock += REPEAT_WINDOW_MS
    record('space-1', 'task', 'task-1')

    assert.equal(sent.length, 2)
  })

  it('sends another item straight away, and the first one again after it', () => {
    record('space-1', 'task', 'task-1')
    record('space-1', 'note', 'task-1')
    record('space-1', 'task', 'task-1')

    assert.deepEqual(sent, [
      ['space-1', 'task', 'task-1'],
      ['space-1', 'note', 'task-1'],
      ['space-1', 'task', 'task-1']
    ])
  })

  it('sends nothing without a space or an id', () => {
    record(null, 'task', 'task-1')
    record('space-1', 'task', null)
    record('space-1', 'task', undefined)

    assert.deepEqual(sent, [])
  })
})
