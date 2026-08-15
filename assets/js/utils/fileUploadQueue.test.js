import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  appendToQueue,
  applyUploadFailure,
  cancelPendingUploads,
  classifyUploadFailure,
  hasUnsentItems,
  hasUploadInFlight,
  MAX_RATE_LIMIT_ATTEMPTS,
  msUntilNextUploadSlot,
  nextQueuedItem,
  RATE_LIMIT_PAUSE_MS,
  rateLimitPauseNotice,
  recordUploadStart,
  removeFromQueue,
  requeueUnsentItems,
  sleepUnlessAborted,
  summarizeQueue,
  UPLOAD_RATE_LIMIT,
  UPLOAD_RATE_WINDOW_MS,
  UPLOAD_STATUS,
  withQueueItem
} from './fileUploadQueue.js'

/**
 * The rules a batch of file uploads follows.
 *
 * Run with `npm test`.
 */

/** A stand in for a File: the queue only ever reads a name and a size off it. */
function fakeFile(name, size = 1024) {
  return { name, size }
}

/** A queue of n files, all still waiting. */
function queueOf(...names) {
  return appendToQueue(
    [],
    names.map((name) => fakeFile(name))
  )
}

/** What handleApiError hands back for each way the upload endpoint refuses. */
const quotaError = {
  status: 422,
  isValidationError: false,
  message:
    'Quota de stockage dépassé : 5000 octets utilisés + 900 octets envoyés > 5200 octets autorisés'
}
const rateLimitError = { status: 429, isValidationError: false, message: 'Rate Limit Exceeded' }
const tooLargeError = {
  status: 422,
  isValidationError: true,
  message: 'Le fichier est trop volumineux'
}
const cancelledError = {
  message: 'canceled',
  originalError: { code: 'ERR_CANCELED', name: 'CanceledError' }
}

describe('appendToQueue', () => {
  it('queues every file that was picked, not just the first', () => {
    const queue = queueOf('un.jpg', 'deux.jpg', 'trois.jpg')

    assert.equal(queue.length, 3)
    assert.deepEqual(
      queue.map((item) => item.name),
      ['un.jpg', 'deux.jpg', 'trois.jpg']
    )
    assert.ok(queue.every((item) => item.status === UPLOAD_STATUS.Queued))
    assert.ok(queue.every((item) => item.progress === 0 && item.error === null))
  })

  it('keeps both files when two share a name, like uploading the same name twice by hand does', () => {
    const queue = queueOf('photo.jpg', 'photo.jpg')

    assert.equal(queue.length, 2)
    assert.notEqual(queue[0].id, queue[1].id)
  })

  it('keeps ids unique when files are added in a second pick', () => {
    const queue = appendToQueue(queueOf('un.jpg', 'deux.jpg'), [fakeFile('trois.jpg')])

    assert.deepEqual(
      queue.map((item) => item.id),
      [1, 2, 3]
    )
  })

  it('adds nothing when the picker was dismissed', () => {
    assert.deepEqual(appendToQueue([], null), [])
    assert.deepEqual(appendToQueue([], undefined), [])
    assert.deepEqual(appendToQueue([], []), [])
  })

  it('carries the file itself through, so the queue is what gets sent', () => {
    const file = fakeFile('un.jpg', 4096)

    assert.equal(appendToQueue([], [file])[0].file, file)
    assert.equal(appendToQueue([], [file])[0].size, 4096)
  })
})

describe('removeFromQueue', () => {
  it('drops the file the member changed their mind about and leaves the rest alone', () => {
    const queue = queueOf('un.jpg', 'deux.jpg', 'trois.jpg')

    assert.deepEqual(
      removeFromQueue(queue, 2).map((item) => item.name),
      ['un.jpg', 'trois.jpg']
    )
  })
})

describe('withQueueItem', () => {
  it('patches one file and returns a new array, so a watcher sees the change', () => {
    const queue = queueOf('un.jpg', 'deux.jpg')
    const patched = withQueueItem(queue, 1, { status: UPLOAD_STATUS.Uploading, progress: 40 })

    assert.notEqual(patched, queue)
    assert.equal(queue[0].status, UPLOAD_STATUS.Queued)
    assert.equal(patched[0].progress, 40)
    assert.equal(patched[1].status, UPLOAD_STATUS.Queued)
  })
})

describe('nextQueuedItem', () => {
  it('hands back the files in the order they were picked', () => {
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg'), 1, {
      status: UPLOAD_STATUS.Uploaded
    })

    assert.equal(nextQueuedItem(queue).name, 'deux.jpg')
  })

  it('is null once nothing is waiting, which is what ends the batch', () => {
    const queue = queueOf('un.jpg').map((item) => ({ ...item, status: UPLOAD_STATUS.Uploaded }))

    assert.equal(nextQueuedItem(queue), null)
    assert.equal(nextQueuedItem([]), null)
  })
})

describe('msUntilNextUploadSlot', () => {
  const now = 1_000_000

  it('does not hold back a batch that fits in the budget', () => {
    const sentAt = Array.from({ length: UPLOAD_RATE_LIMIT - 1 }, (_unused, i) => now - i * 10)

    assert.equal(msUntilNextUploadSlot(sentAt, now), 0)
  })

  it('waits for the oldest send to leave the window once the budget is spent', () => {
    const oldest = now - 50_000
    const sentAt = [oldest, ...Array.from({ length: UPLOAD_RATE_LIMIT - 1 }, () => now - 1000)]

    const wait = msUntilNextUploadSlot(sentAt, now)

    // 10s left on the oldest, plus the margin that keeps the browser clock off the limiter's edge.
    assert.ok(wait >= 10_000, `expected at least 10s, got ${wait}`)
    assert.ok(wait <= 12_000, `expected at most 12s, got ${wait}`)
  })

  it('frees the slot again once the oldest send has aged out', () => {
    const sentAt = Array.from({ length: UPLOAD_RATE_LIMIT }, () => now - UPLOAD_RATE_WINDOW_MS - 1)

    assert.equal(msUntilNextUploadSlot(sentAt, now), 0)
  })

  it('starts from nothing sent', () => {
    assert.equal(msUntilNextUploadSlot([], now), 0)
  })
})

describe('recordUploadStart', () => {
  it('records the send and forgets the ones that left the window', () => {
    const now = 1_000_000
    const sentAt = [now - UPLOAD_RATE_WINDOW_MS - 1, now - 5000]

    assert.deepEqual(recordUploadStart(sentAt, now), [now - 5000, now])
  })
})

describe('rateLimitPauseNotice', () => {
  it('says what the wait is for and how long it lasts', () => {
    assert.equal(
      rateLimitPauseNotice(42_000),
      "Limite d'envoi atteinte (30 fichiers par minute). Reprise dans 42 s."
    )
  })

  it('never announces a wait of zero second', () => {
    assert.equal(
      rateLimitPauseNotice(120),
      "Limite d'envoi atteinte (30 fichiers par minute). Reprise dans 1 s."
    )
  })
})

describe('classifyUploadFailure', () => {
  it('reads a 422 with no violation as the storage quota', () => {
    assert.equal(classifyUploadFailure(quotaError), 'quota')
  })

  it('reads a 422 carrying violations as that one file being refused', () => {
    assert.equal(classifyUploadFailure(tooLargeError), 'file')
  })

  it('reads a 429 as the rate limiter', () => {
    assert.equal(classifyUploadFailure(rateLimitError), 'rate_limit')
  })

  it('recognises the member cancelling, which axios reports as an error like any other', () => {
    assert.equal(classifyUploadFailure(cancelledError), 'cancelled')
    assert.equal(
      classifyUploadFailure({ message: 'canceled', originalError: { name: 'CanceledError' } }),
      'cancelled'
    )
  })

  it('treats anything else as that one file failing', () => {
    assert.equal(classifyUploadFailure({ status: 415, message: 'Type non autorisé' }), 'file')
    assert.equal(classifyUploadFailure({ message: 'Network Error' }), 'file')
    assert.equal(classifyUploadFailure(undefined), 'file')
  })
})

describe('applyUploadFailure', () => {
  it('lets the batch carry on when one file is refused', () => {
    const queue = queueOf('un.jpg', 'deux.jpg', 'trois.jpg')

    const outcome = applyUploadFailure(queue, 1, { status: 415, message: 'Type non autorisé' })

    assert.equal(outcome.stop, false)
    assert.equal(outcome.kind, 'file')
    assert.equal(outcome.pauseMs, 0)
    assert.equal(outcome.notice, null)
    assert.equal(outcome.queue[0].status, UPLOAD_STATUS.Failed)
    assert.equal(outcome.queue[0].error, 'Type non autorisé')
    assert.equal(outcome.queue[1].status, UPLOAD_STATUS.Queued)
    assert.equal(outcome.queue[2].status, UPLOAD_STATUS.Queued)
  })

  it('names an error the server did not word', () => {
    const outcome = applyUploadFailure(queueOf('un.jpg'), 1, { status: 500 })

    assert.equal(outcome.queue[0].error, 'Une erreur est survenue')
  })

  it('stops the batch on the quota and says why, rather than refusing each file in turn', () => {
    const queue = queueOf('un.jpg', 'deux.jpg', 'trois.jpg')

    const outcome = applyUploadFailure(queue, 1, quotaError)

    assert.equal(outcome.stop, true)
    assert.equal(outcome.kind, 'quota')
    assert.equal(outcome.queue[0].status, UPLOAD_STATUS.Failed)
    // The server's own sentence stays on the file that tripped it.
    assert.equal(outcome.queue[0].error, quotaError.message)
    assert.equal(outcome.queue[1].status, UPLOAD_STATUS.Skipped)
    assert.equal(outcome.queue[1].error, 'Quota de stockage atteint, fichier non envoyé')
    assert.equal(outcome.queue[2].status, UPLOAD_STATUS.Skipped)
    assert.equal(
      outcome.notice,
      "Le quota de stockage de l'espace est atteint. Les fichiers restants n'ont pas été envoyés : libérez de la place, puis réessayez."
    )
  })

  it('leaves the files that already landed alone when the quota fills up', () => {
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg', 'trois.jpg'), 1, {
      status: UPLOAD_STATUS.Uploaded,
      progress: 100
    })

    const outcome = applyUploadFailure(queue, 2, quotaError)

    assert.equal(outcome.queue[0].status, UPLOAD_STATUS.Uploaded)
    assert.equal(outcome.queue[0].error, null)
  })

  it('pauses on the rate limiter and puts the file back in the queue, because it has not failed', () => {
    // The status the dialog sets before every request: what the pause has to undo, so the loop
    // picks this same file up again instead of walking past it and stranding it uploading.
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg'), 1, {
      status: UPLOAD_STATUS.Uploading,
      progress: 30
    })

    const outcome = applyUploadFailure(queue, 1, rateLimitError)

    assert.equal(outcome.stop, false)
    assert.equal(outcome.kind, 'rate_limit')
    assert.equal(outcome.pauseMs, RATE_LIMIT_PAUSE_MS)
    assert.equal(outcome.queue[0].status, UPLOAD_STATUS.Queued)
    assert.equal(outcome.queue[0].progress, 0)
    assert.equal(outcome.queue[0].attempts, 1)
    assert.equal(nextQueuedItem(outcome.queue).id, 1)
    assert.equal(outcome.notice, rateLimitPauseNotice(RATE_LIMIT_PAUSE_MS))
  })

  it('gives up on the rate limiter after a bounded number of waits', () => {
    let queue = queueOf('un.jpg', 'deux.jpg')
    let outcome = null

    for (let attempt = 0; attempt < MAX_RATE_LIMIT_ATTEMPTS; attempt++) {
      queue = withQueueItem(queue, 1, { status: UPLOAD_STATUS.Uploading })
      outcome = applyUploadFailure(queue, 1, rateLimitError)
      queue = outcome.queue
    }

    assert.equal(outcome.stop, true)
    // A batch the limiter stopped left nothing half sent, so it is not an interruption.
    assert.equal(outcome.kind, 'rate_limit')
    assert.equal(outcome.pauseMs, 0)
    assert.equal(queue[0].status, UPLOAD_STATUS.Failed)
    assert.equal(queue[0].error, "Limite d'envoi atteinte, fichier non envoyé")
    assert.equal(queue[1].status, UPLOAD_STATUS.Skipped)
    assert.equal(
      outcome.notice,
      "Limite d'envoi atteinte. Les fichiers restants n'ont pas été envoyés : patientez une minute, puis réessayez."
    )
  })

  it('stops everything when the member cancels, and says so on each file', () => {
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg', 'trois.jpg'), 1, {
      status: UPLOAD_STATUS.Uploaded,
      progress: 100
    })

    const outcome = applyUploadFailure(queue, 2, cancelledError)

    assert.equal(outcome.stop, true)
    // What tells Files.vue to read the listing back: only a cut off request can have been stored
    // without the list knowing about it.
    assert.equal(outcome.kind, 'cancelled')
    assert.equal(outcome.queue[0].status, UPLOAD_STATUS.Uploaded)
    assert.equal(outcome.queue[1].status, UPLOAD_STATUS.Cancelled)
    assert.equal(outcome.queue[1].error, 'Import interrompu')
    assert.equal(outcome.queue[2].status, UPLOAD_STATUS.Cancelled)
    assert.equal(outcome.notice, "Import interrompu. Les fichiers restants n'ont pas été envoyés.")
  })
})

describe('cancelPendingUploads', () => {
  it('gives up on the file in flight and everything behind it', () => {
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg', 'trois.jpg'), 2, {
      status: UPLOAD_STATUS.Uploading,
      progress: 60
    })

    const cancelled = cancelPendingUploads(queue)

    assert.equal(cancelled.queue[1].status, UPLOAD_STATUS.Cancelled)
    assert.equal(cancelled.queue[1].progress, 0)
    assert.equal(cancelled.queue[2].status, UPLOAD_STATUS.Cancelled)
    assert.equal(
      cancelled.notice,
      "Import interrompu. Les fichiers restants n'ont pas été envoyés."
    )
  })

  it('gives up on a batch stopped mid pause, where nothing is in flight at all', () => {
    const queue = withQueueItem(queueOf('un.jpg', 'deux.jpg'), 1, {
      status: UPLOAD_STATUS.Uploaded,
      progress: 100
    })

    const cancelled = cancelPendingUploads(queue)

    assert.equal(cancelled.queue[0].status, UPLOAD_STATUS.Uploaded)
    assert.equal(cancelled.queue[1].status, UPLOAD_STATUS.Cancelled)
  })

  it('leaves the files it is already done with alone', () => {
    const stopped = applyUploadFailure(queueOf('un.jpg', 'deux.jpg'), 1, quotaError).queue

    const cancelled = cancelPendingUploads(stopped)

    assert.equal(cancelled.queue[0].status, UPLOAD_STATUS.Failed)
    assert.equal(cancelled.queue[0].error, quotaError.message)
    assert.equal(cancelled.queue[1].status, UPLOAD_STATUS.Skipped)
  })
})

describe('hasUploadInFlight', () => {
  it('is true only while a request is actually on the wire', () => {
    const queue = queueOf('un.jpg', 'deux.jpg')

    assert.equal(hasUploadInFlight(queue), false)
    assert.equal(
      hasUploadInFlight(withQueueItem(queue, 1, { status: UPLOAD_STATUS.Uploading })),
      true
    )
  })

  it('is false during a pause, which is why closing then costs no refetch', () => {
    const paused = applyUploadFailure(
      withQueueItem(queueOf('un.jpg', 'deux.jpg'), 1, { status: UPLOAD_STATUS.Uploading }),
      1,
      rateLimitError
    ).queue

    assert.equal(hasUploadInFlight(paused), false)
  })
})

describe('sleepUnlessAborted', () => {
  it('runs to time when nothing calls the batch off', async () => {
    assert.equal(await sleepUnlessAborted(1), false)
    assert.equal(await sleepUnlessAborted(1, new AbortController().signal), false)
  })

  it('is cut short the moment the batch is stopped, not when the wait was due to end', async () => {
    const controller = new AbortController()
    const startedAt = Date.now()

    const waiting = sleepUnlessAborted(30_000, controller.signal)
    controller.abort()

    assert.equal(await waiting, true)
    assert.ok(Date.now() - startedAt < 1000, 'the stop button must not wait out the pause')
  })

  it('does not start a wait the batch has already been stopped out of', async () => {
    const controller = new AbortController()
    controller.abort()

    assert.equal(await sleepUnlessAborted(30_000, controller.signal), true)
  })
})

describe('requeueUnsentItems', () => {
  it('retries the failures and never sends an imported file twice', () => {
    const queue = applyUploadFailure(
      withQueueItem(queueOf('un.jpg', 'deux.jpg', 'trois.jpg'), 1, {
        status: UPLOAD_STATUS.Uploaded,
        progress: 100
      }),
      2,
      quotaError
    ).queue

    const retried = requeueUnsentItems(queue)

    assert.equal(retried[0].status, UPLOAD_STATUS.Uploaded)
    assert.equal(retried[1].status, UPLOAD_STATUS.Queued)
    assert.equal(retried[1].error, null)
    assert.equal(retried[2].status, UPLOAD_STATUS.Queued)
  })

  it('clears the wait count, so a retry gets the full budget again', () => {
    let queue = queueOf('un.jpg')
    for (let attempt = 0; attempt < MAX_RATE_LIMIT_ATTEMPTS; attempt++) {
      queue = applyUploadFailure(queue, 1, rateLimitError).queue
    }

    assert.equal(queue[0].attempts, MAX_RATE_LIMIT_ATTEMPTS)
    assert.equal(requeueUnsentItems(queue)[0].attempts, 0)
  })
})

describe('hasUnsentItems', () => {
  it('is what puts the retry button on screen', () => {
    const allDone = queueOf('un.jpg').map((item) => ({ ...item, status: UPLOAD_STATUS.Uploaded }))

    assert.equal(hasUnsentItems(allDone), false)
    assert.equal(hasUnsentItems(applyUploadFailure(queueOf('un.jpg'), 1, quotaError).queue), true)
  })
})

describe('summarizeQueue', () => {
  it('counts nothing on an empty queue', () => {
    const summary = summarizeQueue([])

    assert.equal(summary.total, 0)
    assert.equal(summary.percent, 0)
    assert.equal(summary.isFinished, false)
  })

  it('follows the batch through, file by file', () => {
    let queue = queueOf('un.jpg', 'deux.jpg', 'trois.jpg', 'quatre.jpg')
    assert.equal(summarizeQueue(queue).position, 1)
    assert.equal(summarizeQueue(queue).percent, 0)

    queue = withQueueItem(queue, 1, { status: UPLOAD_STATUS.Uploaded, progress: 100 })
    queue = withQueueItem(queue, 2, { status: UPLOAD_STATUS.Uploading, progress: 50 })

    const summary = summarizeQueue(queue)
    assert.equal(summary.position, 2)
    assert.equal(summary.uploaded, 1)
    assert.equal(summary.percent, 38)
    assert.equal(summary.isFinished, false)
  })

  it('counts a file nothing more will happen to as finished with, whatever became of it', () => {
    const queue = applyUploadFailure(queueOf('un.jpg', 'deux.jpg'), 1, quotaError).queue
    const summary = summarizeQueue(queue)

    assert.equal(summary.percent, 100)
    assert.equal(summary.isFinished, true)
    assert.equal(summary.unsent, 2)
    assert.equal(summary.uploaded, 0)
  })

  it('words a batch that went through', () => {
    const one = queueOf('un.jpg').map((item) => ({ ...item, status: UPLOAD_STATUS.Uploaded }))
    const three = queueOf('un.jpg', 'deux.jpg', 'trois.jpg').map((item) => ({
      ...item,
      status: UPLOAD_STATUS.Uploaded
    }))

    assert.equal(summarizeQueue(one).label, '1 fichier importé')
    assert.equal(summarizeQueue(three).label, '3 fichiers importés')
  })

  it('words a batch that partly went through, without calling a held back file a failure', () => {
    const queue = applyUploadFailure(
      withQueueItem(queueOf('un.jpg', 'deux.jpg', 'trois.jpg'), 1, {
        status: UPLOAD_STATUS.Uploaded,
        progress: 100
      }),
      2,
      quotaError
    ).queue

    assert.equal(summarizeQueue(queue).label, '1 fichier importé, 2 non envoyés')
  })

  it('words a batch that got nowhere', () => {
    const queue = applyUploadFailure(queueOf('un.jpg'), 1, quotaError).queue

    assert.equal(summarizeQueue(queue).label, 'Aucun fichier importé, 1 non envoyé')
  })
})
