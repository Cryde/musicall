/**
 * The rules a batch of file uploads follows, kept out of the dialog so they can be tested.
 *
 * Files go up one at a time, never in parallel: the endpoint is rate limited per user and the
 * quota is checked per request, so a fan-out would trip both at once and nobody could say which
 * of twenty photos actually landed. Sequential means every file gets its own visible outcome.
 *
 * The three ways a batch dies are not the same event and must not be reported the same way:
 *
 * - a file the server refuses (wrong type, too large) is that file's problem, the batch carries on;
 * - the storage quota being reached is the space's problem, so every file still waiting is marked
 *   as not sent rather than being pushed at a server that is going to refuse each one in turn,
 *   which is what turns one full disk into twenty red lines;
 * - the rate limit is nobody's problem, it is a wait, so the batch pauses and resumes on its own.
 *
 * Duplicate names are deliberately left alone. The API has no unique constraint on a file's name
 * and stores it under a hashed path, so uploading the same name twice makes two independent files,
 * exactly as doing it twice by hand does. The queue keeps both entries for the same reason.
 *
 * Pure on purpose, so the rules are tested without a browser. See fileUploadQueue.test.js.
 */

/**
 * What the API accepts per user.
 *
 * Hand copied from `config/packages/rate_limiter.yaml`, key `band_space_file_upload` (`limit` and
 * `interval`). Nothing checks the two agree, because a JS test cannot read the PHP config: changing
 * one means changing the other by hand. That file carries the same warning pointing back here.
 */
export const UPLOAD_RATE_LIMIT = 30
export const UPLOAD_RATE_WINDOW_MS = 60000

/**
 * Added to every computed wait. The limiter's window and the browser's clock are not the same
 * clock, so asking for the slot the very millisecond it frees is how a paced batch still gets a 429.
 */
const SLOT_MARGIN_MS = 1500

/** How long the batch waits after the server itself answers 429, and how often it may do so. */
export const RATE_LIMIT_PAUSE_MS = 20000
export const MAX_RATE_LIMIT_ATTEMPTS = 3

export const UPLOAD_STATUS = Object.freeze({
  Queued: 'queued',
  Uploading: 'uploading',
  Uploaded: 'uploaded',
  Failed: 'failed',
  /** Never sent, because something upstream made sending pointless. Retryable. */
  Skipped: 'skipped',
  Cancelled: 'cancelled'
})

/** Statuses a retry can put back in the queue. */
const RETRYABLE_STATUSES = [UPLOAD_STATUS.Failed, UPLOAD_STATUS.Skipped, UPLOAD_STATUS.Cancelled]

/** Statuses nothing more is going to happen to. */
const TERMINAL_STATUSES = [UPLOAD_STATUS.Uploaded, ...RETRYABLE_STATUSES]

const QUOTA_REACHED_MESSAGE = 'Quota de stockage atteint, fichier non envoyé'
const QUOTA_BATCH_NOTICE =
  "Le quota de stockage de l'espace est atteint. Les fichiers restants n'ont pas été envoyés : libérez de la place, puis réessayez."
const RATE_LIMIT_REACHED_MESSAGE = "Limite d'envoi atteinte, fichier non envoyé"
const RATE_LIMIT_BATCH_NOTICE =
  "Limite d'envoi atteinte. Les fichiers restants n'ont pas été envoyés : patientez une minute, puis réessayez."
const CANCELLED_MESSAGE = 'Import interrompu'
const CANCELLED_BATCH_NOTICE = "Import interrompu. Les fichiers restants n'ont pas été envoyés."

/**
 * Adds files to the end of the queue, keeping whatever is already there.
 *
 * Two files sharing a name both stay, with their own id: see the note at the top of this file.
 *
 * @param {{id: number}[]} queue
 * @param {Iterable<{name: string, size: number}>} files a FileList or an array of File
 * @returns {Object[]} a new queue
 */
export function appendToQueue(queue, files) {
  let nextId = queue.reduce((max, item) => Math.max(max, item.id), 0) + 1

  const added = Array.from(files ?? []).map((file) => ({
    id: nextId++,
    file,
    name: file.name,
    size: file.size,
    status: UPLOAD_STATUS.Queued,
    progress: 0,
    error: null,
    /** How many times the rate limiter has pushed this file back. */
    attempts: 0
  }))

  return [...queue, ...added]
}

/**
 * @param {{id: number}[]} queue
 * @param {number} id
 * @returns {Object[]} a new queue
 */
export function removeFromQueue(queue, id) {
  return queue.filter((item) => item.id !== id)
}

/**
 * @param {{id: number}[]} queue
 * @param {number} id
 * @param {Object} patch
 * @returns {Object[]} a new queue, so a watcher sees the change
 */
export function withQueueItem(queue, id, patch) {
  return queue.map((item) => (item.id === id ? { ...item, ...patch } : item))
}

/**
 * @param {{status: string}[]} queue
 * @returns {Object|null} the file to send next, null when the batch is over
 */
export function nextQueuedItem(queue) {
  return queue.find((item) => item.status === UPLOAD_STATUS.Queued) ?? null
}

/**
 * Puts everything that did not make it back in the queue, so one button retries the failures
 * without touching the files that already landed.
 *
 * @param {{status: string}[]} queue
 * @returns {Object[]} a new queue
 */
export function requeueUnsentItems(queue) {
  return queue.map((item) =>
    RETRYABLE_STATUSES.includes(item.status)
      ? { ...item, status: UPLOAD_STATUS.Queued, progress: 0, error: null, attempts: 0 }
      : item
  )
}

/**
 * @param {{status: string}[]} queue
 * @returns {boolean}
 */
export function hasUnsentItems(queue) {
  return queue.some((item) => RETRYABLE_STATUSES.includes(item.status))
}

/**
 * Drops the timestamps that have left the limiter's window and records a new send.
 *
 * @param {number[]} sentAt
 * @param {number} now
 * @returns {number[]} a new array
 */
export function recordUploadStart(sentAt, now) {
  return [...withinWindow(sentAt, now), now]
}

/**
 * How long to wait before sending the next file so the batch stays roughly inside the server's
 * budget. A batch bigger than the limit is the case this exists for: paced, fifty photos trickle up
 * over a couple of minutes instead of thirty arriving and twenty being refused outright.
 *
 * It makes a 429 unlikely, not impossible, and deliberately so. This is a true sliding log, exact
 * timestamps in a rolling window. Symfony's `sliding_window` policy is a cheaper approximation:
 * two fixed windows blended, `floor(previousWindowHits * (1 - elapsedFraction) + currentHits)`.
 * The two disagree most on a fast bursty batch. Thirty small files sent inside the first fifteen
 * seconds of a window leave this log counting slots free while the server's blend still reports
 * thirty and refuses. Reconciling them would mean reimplementing the server's approximation and
 * keeping it in step, for a case the retry path already absorbs, so the pacing stays honest about
 * being an estimate and applyUploadFailure's rate_limit branch is what actually closes the gap.
 *
 * The error is on the safe side: Symfony's limiter never records a rejected consume, so a 429 costs
 * no server budget while this log still counts the attempt.
 *
 * @param {number[]} sentAt when each of the recent uploads started
 * @param {number} now
 * @returns {number} 0 when a slot is free
 */
export function msUntilNextUploadSlot(sentAt, now) {
  const recent = withinWindow(sentAt, now).sort((a, b) => a - b)
  if (recent.length < UPLOAD_RATE_LIMIT) {
    return 0
  }

  // The one whose expiry frees a slot, which is the oldest while the batch sends one at a time.
  const freeingSlot = recent[recent.length - UPLOAD_RATE_LIMIT]

  return Math.max(0, freeingSlot + UPLOAD_RATE_WINDOW_MS - now + SLOT_MARGIN_MS)
}

/**
 * @param {number[]} sentAt
 * @param {number} now
 * @returns {number[]}
 */
function withinWindow(sentAt, now) {
  return sentAt.filter((timestamp) => now - timestamp < UPLOAD_RATE_WINDOW_MS)
}

/** @returns {string} what the pause is for and roughly how long it lasts */
export function rateLimitPauseNotice(waitMs) {
  const seconds = Math.max(1, Math.ceil(waitMs / 1000))

  return `Limite d'envoi atteinte (${UPLOAD_RATE_LIMIT} fichiers par minute). Reprise dans ${seconds} s.`
}

/**
 * Reads what the API refused with.
 *
 * The quota is the one refusal with no violation attached: BandSpaceFileUploadProcessor answers 422
 * from QuotaExceededException only, every other rejection it raises is a 400, a 403, a 409 or a 415,
 * and API Platform's own 422 always carries violations. Matching on that rather than on the French
 * sentence is what let the wording be rewritten without touching this file.
 *
 * @param {{status?: number, isValidationError?: boolean, originalError?: {code?: string, name?: string}}} error
 * @returns {'quota'|'rate_limit'|'cancelled'|'file'}
 */
export function classifyUploadFailure(error) {
  const cause = error?.originalError
  if (cause?.code === 'ERR_CANCELED' || cause?.name === 'CanceledError') {
    return 'cancelled'
  }
  if (error?.status === 429) {
    return 'rate_limit'
  }
  if (error?.status === 422 && !error?.isValidationError) {
    return 'quota'
  }

  return 'file'
}

/**
 * What the batch does about a file the server just refused.
 *
 * @param {Object[]} queue
 * @param {number} id the file that failed
 * @param {Object} error a normalised error from handleApiError
 * @returns {{queue: Object[], kind: string, notice: string|null, pauseMs: number, stop: boolean}}
 *          `stop` ends the batch, `pauseMs` holds it before it tries the same file again, and
 *          `kind` is what the caller needs to tell a batch that was cut off from one that was
 *          refused: only the first can have left a file on the server nothing knows about.
 */
export function applyUploadFailure(queue, id, error) {
  const kind = classifyUploadFailure(error)

  if (kind === 'cancelled') {
    const cancelled = cancelPendingUploads(queue)

    return { queue: cancelled.queue, kind, notice: cancelled.notice, pauseMs: 0, stop: true }
  }

  if (kind === 'rate_limit') {
    const attempts = (queue.find((item) => item.id === id)?.attempts ?? 0) + 1
    if (attempts < MAX_RATE_LIMIT_ATTEMPTS) {
      // Put back in the queue, not failed: this file has been asked to wait its turn, and the loop
      // picks the queue up again after the pause, so this is what makes the same file the next one
      // tried. Leaving it uploading would strand it there spinning while the batch moved past it.
      return {
        queue: withQueueItem(queue, id, {
          status: UPLOAD_STATUS.Queued,
          attempts,
          progress: 0
        }),
        kind,
        notice: rateLimitPauseNotice(RATE_LIMIT_PAUSE_MS),
        pauseMs: RATE_LIMIT_PAUSE_MS,
        stop: false
      }
    }

    const failed = withQueueItem(queue, id, {
      status: UPLOAD_STATUS.Failed,
      attempts,
      error: RATE_LIMIT_REACHED_MESSAGE
    })

    return {
      queue: markUnsent(failed, UPLOAD_STATUS.Skipped, RATE_LIMIT_REACHED_MESSAGE),
      kind,
      notice: RATE_LIMIT_BATCH_NOTICE,
      pauseMs: 0,
      stop: true
    }
  }

  if (kind === 'quota') {
    // The server's own sentence is kept on the file that tripped it: QuotaExceededException already
    // writes the figures in French units and states the shortfall, and this is the only place in the
    // batch report where the numbers appear at all.
    const failed = withQueueItem(queue, id, {
      status: UPLOAD_STATUS.Failed,
      error: messageOf(error)
    })

    return {
      queue: markUnsent(failed, UPLOAD_STATUS.Skipped, QUOTA_REACHED_MESSAGE),
      kind,
      notice: QUOTA_BATCH_NOTICE,
      pauseMs: 0,
      stop: true
    }
  }

  return {
    queue: withQueueItem(queue, id, { status: UPLOAD_STATUS.Failed, error: messageOf(error) }),
    kind,
    notice: null,
    pauseMs: 0,
    stop: false
  }
}

/**
 * Everything the batch has not finished with is given up on.
 *
 * Used for both ways a batch is cancelled, and they are not the same moment: a request cut off in
 * flight leaves that file uploading, while a batch stopped during one of its own pauses has nothing
 * in flight at all and only queued files to abandon. Working off "not terminal yet" covers both
 * without the caller having to say which it is.
 *
 * @param {{status: string}[]} queue
 * @returns {{queue: Object[], notice: string}}
 */
export function cancelPendingUploads(queue) {
  return {
    queue: queue.map((item) =>
      TERMINAL_STATUSES.includes(item.status)
        ? item
        : { ...item, status: UPLOAD_STATUS.Cancelled, progress: 0, error: CANCELLED_MESSAGE }
    ),
    notice: CANCELLED_BATCH_NOTICE
  }
}

/**
 * Whether a request is on the wire right now.
 *
 * This is what separates a batch abandoned mid request, which may have left a file on the server
 * that no listing knows about, from one abandoned during a pause, which cannot have. Only the first
 * is worth making the file list refetch itself over.
 *
 * @param {{status: string}[]} queue
 * @returns {boolean}
 */
export function hasUploadInFlight(queue) {
  return queue.some((item) => item.status === UPLOAD_STATUS.Uploading)
}

/**
 * Waits, unless the batch is called off first.
 *
 * The batch spends most of a rate limited run inside one of these, with no request on the wire, so
 * a stop button that only aborts requests would sit there doing nothing for up to twenty seconds.
 *
 * @param {number} ms
 * @param {AbortSignal} [signal]
 * @returns {Promise<boolean>} true when it was cut short rather than run to time
 */
export function sleepUnlessAborted(ms, signal) {
  if (signal?.aborted) {
    return Promise.resolve(true)
  }

  return new Promise((resolve) => {
    let timer = null
    const onAbort = () => {
      clearTimeout(timer)
      resolve(true)
    }

    timer = setTimeout(() => {
      signal?.removeEventListener('abort', onAbort)
      resolve(false)
    }, ms)
    signal?.addEventListener('abort', onAbort, { once: true })
  })
}

/**
 * @param {Object[]} queue
 * @param {string} status
 * @param {string} reason
 * @returns {Object[]} every file still waiting, given the same outcome and the same reason
 */
function markUnsent(queue, status, reason) {
  return queue.map((item) =>
    item.status === UPLOAD_STATUS.Queued ? { ...item, status, error: reason } : item
  )
}

/** @returns {string} */
function messageOf(error) {
  return error?.message || 'Une erreur est survenue'
}

/**
 * The counts the dialog and the toast are both built from.
 *
 * `percent` treats a file that failed as finished with, because it drives the batch's own progress
 * bar: what is left to wait for, not what succeeded.
 *
 * @param {{status: string, progress: number}[]} queue
 * @returns {{total: number, uploaded: number, unsent: number, done: number, position: number,
 *            isFinished: boolean, percent: number, label: string}}
 */
export function summarizeQueue(queue) {
  const total = queue.length
  const uploaded = countByStatus(queue, UPLOAD_STATUS.Uploaded)
  const unsent = queue.filter((item) => RETRYABLE_STATUSES.includes(item.status)).length
  const done = uploaded + unsent

  const percent =
    total === 0
      ? 0
      : Math.round(
          queue.reduce(
            (sum, item) => sum + (TERMINAL_STATUSES.includes(item.status) ? 100 : item.progress),
            0
          ) / total
        )

  return {
    total,
    uploaded,
    unsent,
    done,
    /** Which file the batch is on, one based, for « Fichier 3 sur 20 ». */
    position: Math.min(total, done + 1),
    isFinished: total > 0 && done === total,
    percent,
    label: outcomeLabel(uploaded, unsent)
  }
}

/** @returns {number} */
function countByStatus(queue, status) {
  return queue.filter((item) => item.status === status).length
}

/**
 * What the batch did, in one French sentence.
 *
 * Files that were never sent are counted apart from the ones that were refused, because « échec »
 * would be a lie for the nineteen the queue held back when the quota filled up.
 *
 * @param {number} uploaded
 * @param {number} unsent
 * @returns {string}
 */
function outcomeLabel(uploaded, unsent) {
  const importedPart =
    uploaded === 0
      ? 'Aucun fichier importé'
      : `${uploaded} fichier${uploaded > 1 ? 's' : ''} importé${uploaded > 1 ? 's' : ''}`

  if (unsent === 0) {
    return importedPart
  }

  return `${importedPart}, ${unsent} non ${unsent > 1 ? 'envoyés' : 'envoyé'}`
}
