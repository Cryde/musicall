import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it, mock } from 'node:test'
import { createPdfPreviewScheduler } from './pdfPreviewScheduler.js'

/**
 * The tech rider preview (#1092): one Gotenberg render per settled batch of edits, only while the
 * preview is open, and never two at once.
 *
 * Run with `npm test`.
 */

const DELAY_MS = 3000

async function settle() {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve()
  }
}

/** A render the test answers by hand, so it can act while one is out. */
function setup() {
  let renders = 0
  let inFlight = 0
  let maxInFlight = 0
  const pending = []
  const busy = []
  const scheduler = createPdfPreviewScheduler({
    onBusy: (isBusy) => busy.push(isBusy),
    render: () =>
      new Promise((resolve, reject) => {
        renders++
        inFlight++
        maxInFlight = Math.max(maxInFlight, inFlight)
        pending.push({
          resolve: () => {
            inFlight--
            resolve()
          },
          reject: () => {
            inFlight--
            reject(new Error('Gotenberg down'))
          }
        })
      }),
    delayMs: DELAY_MS
  })

  async function finish({ fail = false } = {}) {
    const next = pending.shift()
    if (fail) {
      next.reject()
    } else {
      next.resolve()
    }
    await settle()
  }

  return {
    scheduler,
    finish,
    busy,
    renders: () => renders,
    maxInFlight: () => maxInFlight
  }
}

describe('createPdfPreviewScheduler', () => {
  beforeEach(() => mock.timers.enable({ apis: ['setTimeout'] }))
  afterEach(() => mock.timers.reset())

  it('renders at once when started', () => {
    const { scheduler, renders } = setup()

    scheduler.start()

    assert.equal(renders(), 1)
  })

  it('never renders before it is started', () => {
    const { scheduler, renders } = setup()

    scheduler.changed()
    mock.timers.tick(DELAY_MS * 2)

    assert.equal(renders(), 0)
  })

  it('waits for edits to settle before rendering again', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()
    await finish()

    scheduler.changed()
    mock.timers.tick(DELAY_MS - 1)
    scheduler.changed()
    mock.timers.tick(DELAY_MS - 1)
    assert.equal(renders(), 1)

    mock.timers.tick(1)
    assert.equal(renders(), 2)
  })

  it('picks up a change made during a render once that render ends, never overlapping it', async () => {
    const { scheduler, finish, renders, maxInFlight } = setup()
    scheduler.start()

    scheduler.changed()
    mock.timers.tick(DELAY_MS)
    assert.equal(renders(), 1)

    await finish()
    assert.equal(renders(), 2)
    assert.equal(maxInFlight(), 1)
  })

  it('still waits out the delay for a change made just before a render ends', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()

    scheduler.changed()
    await finish()
    assert.equal(renders(), 1)

    mock.timers.tick(DELAY_MS)
    assert.equal(renders(), 2)
  })

  it('does not render again when nothing changed during a render', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()
    await finish()

    mock.timers.tick(DELAY_MS * 2)

    assert.equal(renders(), 1)
  })

  it('drops a pending render when stopped', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()
    await finish()

    scheduler.changed()
    scheduler.stop()
    mock.timers.tick(DELAY_MS)

    assert.equal(renders(), 1)
  })

  it('stays idle once stopped mid render, whatever changed', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()
    scheduler.changed()
    scheduler.stop()

    await finish()

    assert.equal(renders(), 1)
  })

  it('renders again on a retry after a failure', async () => {
    const { scheduler, finish, renders } = setup()
    scheduler.start()
    await finish({ fail: true })

    scheduler.refresh()

    assert.equal(renders(), 2)
  })

  it('is busy from a change until its PDF is shown, through the wait and the render', async () => {
    const { scheduler, finish, busy } = setup()
    scheduler.start()
    await finish()
    assert.deepEqual(busy, [true, false])

    scheduler.changed()
    assert.deepEqual(busy, [true, false, true])

    mock.timers.tick(DELAY_MS)
    assert.deepEqual(busy, [true, false, true], 'the render follows the wait without a gap')

    await finish()
    assert.deepEqual(busy, [true, false, true, false])
  })

  it('stays busy across a render that a change made meanwhile has to follow', async () => {
    const { scheduler, finish, busy } = setup()
    scheduler.start()
    scheduler.changed()
    mock.timers.tick(DELAY_MS)

    await finish()

    assert.deepEqual(busy, [true])
  })

  it('stops being busy when a pending render is dropped', async () => {
    const { scheduler, finish, busy } = setup()
    scheduler.start()
    await finish()
    scheduler.changed()

    scheduler.stop()

    assert.deepEqual(busy, [true, false, true, false])
  })
})
