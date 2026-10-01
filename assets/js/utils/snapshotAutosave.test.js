import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it, mock } from 'node:test'
import { createSnapshotAutosave } from './snapshotAutosave.js'

/**
 * The patch list and the stage plot autosave their whole state (#1089). What matters is that the
 * server ends up holding what is on screen, whatever the user does while a save is out, and that the
 * status never claims "saved" over edits the server has not seen.
 *
 * Run with `npm test`. Node's fake timers, as in debouncedSaver.test.js.
 */

const DELAY_MS = 1500

async function settle() {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve()
  }
}

/** A screen state the test edits, a server that records what it got, and the states reported. */
function setup({ validate } = {}) {
  const screen = { value: 'A' }
  const sent = []
  const states = []
  let release = null
  const autosave = createSnapshotAutosave({
    serialise: () => JSON.stringify(screen.value),
    save: (payload) =>
      new Promise((resolve) => {
        sent.push(payload)
        release = resolve
      }),
    onState: (state) => states.push(state),
    validate,
    delayMs: DELAY_MS
  })

  function edit(value) {
    screen.value = value
    autosave.changed()
  }

  return { autosave, sent, states, edit, answer: () => release() }
}

describe('createSnapshotAutosave', () => {
  beforeEach(() => mock.timers.enable({ apis: ['setTimeout'] }))
  afterEach(() => mock.timers.reset())

  it('saves the edit once the delay has passed', async () => {
    const { sent, states, edit, answer } = setup()

    edit('B')
    mock.timers.tick(DELAY_MS)
    answer()
    await settle()

    assert.deepEqual(sent, ['B'])
    assert.deepEqual(states, ['pending', 'saving', 'saved'])
  })

  it('sends the undo when it happens while the edit is being saved', async () => {
    const { sent, states, edit, answer } = setup()

    edit('B')
    mock.timers.tick(DELAY_MS)
    edit('A')
    answer()
    await settle()
    mock.timers.tick(DELAY_MS)
    answer()
    await settle()

    assert.deepEqual(sent, ['B', 'A'])
    assert.equal(states.at(-1), 'saved')
  })

  it('sends what was typed during a save rather than calling it saved', async () => {
    const { sent, states, edit, answer } = setup()

    edit('B')
    mock.timers.tick(DELAY_MS)
    edit('C')
    answer()
    await settle()

    assert.equal(states.at(-1), 'pending')
    mock.timers.tick(DELAY_MS)
    answer()
    await settle()

    assert.deepEqual(sent, ['B', 'C'])
    assert.equal(states.at(-1), 'saved')
  })

  it('goes back to saved without a request when an edit is undone in time', () => {
    const { sent, states, edit } = setup()

    edit('B')
    edit('A')
    mock.timers.tick(DELAY_MS)

    assert.deepEqual(sent, [])
    assert.deepEqual(states, ['pending', 'saved'])
  })

  it('holds a save the editor knows the server would refuse', async () => {
    const { sent, states, edit } = setup({ validate: () => 'Canal : ce champ est requis' })

    edit('B')
    mock.timers.tick(DELAY_MS)
    await settle()

    assert.deepEqual(sent, [])
    assert.equal(states.at(-1), 'error')
  })

  it('sends the pending edit on flush', () => {
    const { autosave, sent, edit } = setup()

    edit('B')
    autosave.flush()

    assert.deepEqual(sent, ['B'])
  })

  it('treats a refresh carrying the saved state as a confirmation', async () => {
    const { autosave, edit, answer } = setup()

    edit('B')
    mock.timers.tick(DELAY_MS)
    assert.equal(autosave.isSaving(), true)
    answer()
    await settle()

    assert.equal(autosave.isSaving(), false)
    assert.equal(autosave.isConfirmed(JSON.stringify('B')), true)
    assert.equal(autosave.isDirty(), false)
  })
})
