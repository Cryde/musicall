import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  classifyNoteSaveFailure,
  SAVE_RETRY_DELAYS_MS,
  saveNoteContentWithRetries
} from './noteContentSave.js'

/**
 * These pin what a failed autosave does, which used to be nothing at all: the status went to
 * 'error', an eleven pixel word appeared in the editor header, and the text sat there until the tab
 * closed.
 *
 * The half that matters most is what is not retried. A body write names the revision it was made
 * against, so retrying is only safe while the payload stays untouched: a retry that re-sends the
 * same revision is refused when somebody else has written since, and refusing is the whole point.
 *
 * Run with `npm test`.
 */

const savedNote = { id: 'note-1', content_version: 4 }

/** Records how long each retry waited, and returns immediately so the tests do not sleep. */
function fakeWait() {
  const waited = []
  const wait = async (ms) => {
    waited.push(ms)
  }

  return { waited, wait }
}

function apiError(status) {
  const error = new Error('échec')
  error.status = status

  return error
}

/** No `status` at all is what handleApiError produces when no answer ever came back. */
function networkError() {
  return new Error('Network Error')
}

describe('classifyNoteSaveFailure', () => {
  it('reads a refused revision as a conflict, and never retries it', () => {
    assert.deepEqual(classifyNoteSaveFailure(apiError(409)), {
      status: 'conflict',
      canRetry: false
    })
  })

  it('does not retry a write that named no revision, because trying again names none either', () => {
    assert.deepEqual(classifyNoteSaveFailure(apiError(428)), { status: 'error', canRetry: false })
  })

  it('does not retry anything else the server decided', () => {
    for (const status of [400, 403, 404, 422]) {
      assert.deepEqual(
        classifyNoteSaveFailure(apiError(status)),
        { status: 'error', canRetry: false },
        `HTTP ${status}`
      )
    }
  })

  it('retries a server error and a request that never got an answer', () => {
    assert.deepEqual(classifyNoteSaveFailure(apiError(500)), { status: 'error', canRetry: true })
    assert.deepEqual(classifyNoteSaveFailure(apiError(503)), { status: 'error', canRetry: true })
    assert.deepEqual(classifyNoteSaveFailure(networkError()), { status: 'error', canRetry: true })
  })
})

describe('saveNoteContentWithRetries', () => {
  it('returns the saved note and never waits when the first attempt works', async () => {
    const { waited, wait } = fakeWait()

    const outcome = await saveNoteContentWithRetries({ save: async () => savedNote, wait })

    assert.deepEqual(outcome, { status: 'saved', note: savedNote })
    assert.deepEqual(waited, [])
  })

  it('retries a dropped connection and saves without bothering the member', async () => {
    const { waited, wait } = fakeWait()
    let attempts = 0
    const save = async () => {
      attempts++
      if (attempts === 1) throw networkError()

      return savedNote
    }

    const outcome = await saveNoteContentWithRetries({ save, wait })

    assert.deepEqual(outcome, { status: 'saved', note: savedNote })
    assert.equal(attempts, 2)
    assert.deepEqual(waited, [SAVE_RETRY_DELAYS_MS[0]])
  })

  it('gives up after the last delay and reports the failure', async () => {
    const { waited, wait } = fakeWait()
    let attempts = 0
    const save = async () => {
      attempts++
      throw networkError()
    }

    const outcome = await saveNoteContentWithRetries({ save, delaysMs: [10, 20], wait })

    assert.equal(outcome.status, 'error')
    assert.equal(attempts, 3, 'the first attempt plus one per delay')
    assert.deepEqual(waited, [10, 20])
  })

  /**
   * The retry that must not happen. Sending it again means sending the same refused document, and
   * sending it with a fresh revision means erasing whatever the other member wrote.
   */
  it('stops on a refused revision, on the very first answer', async () => {
    const { waited, wait } = fakeWait()
    let attempts = 0
    const save = async () => {
      attempts++
      throw apiError(409)
    }

    const outcome = await saveNoteContentWithRetries({ save, wait })

    assert.equal(outcome.status, 'conflict')
    assert.equal(attempts, 1)
    assert.deepEqual(waited, [])
  })

  /**
   * The case the unchanged payload buys: between the dropped connection and the retry, somebody
   * else wrote to the note. The retry names the revision the text was written against, so it comes
   * back refused rather than replacing their paragraph.
   */
  it('reports a conflict when a retry lands after another member has written', async () => {
    const { wait } = fakeWait()
    let attempts = 0
    const save = async () => {
      attempts++
      throw attempts === 1 ? networkError() : apiError(409)
    }

    const outcome = await saveNoteContentWithRetries({ save, wait })

    assert.equal(outcome.status, 'conflict')
    assert.equal(attempts, 2, 'the conflict ends it, the second delay is never used')
  })

  it('sends the same payload on every attempt', async () => {
    const { wait } = fakeWait()
    const sent = []
    const payload = { content: { type: 'doc' }, expected_content_version: 3 }
    let attempts = 0
    const save = async () => {
      attempts++
      sent.push(payload)
      if (attempts < 3) throw networkError()

      return savedNote
    }

    await saveNoteContentWithRetries({ save, wait })

    assert.equal(sent.length, 3)
    assert.deepEqual(sent, [payload, payload, payload])
  })
})
